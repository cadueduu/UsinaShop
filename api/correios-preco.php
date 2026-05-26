<?php
require_once dirname(__DIR__) . '/includes/config.php';

function correios_jwt_is_expired(string $jwt): bool {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return true;
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!is_array($payload) || !isset($payload['exp'])) return false;
    return intval($payload['exp']) < time();
}

function correios_normalize_weight_to_grams($v): ?int {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    $n = floatval($v);
    if ($n <= 0) return null;
    if ($n > 30) return (int) round($n);
    return (int) round($n * 1000);
}

function correios_normalize_brl_value($v): ?float {
    if ($v === null || $v === '') return null;
    if (is_string($v)) {
        $s = trim($v);
        if ($s === '') return null;
        $s = str_replace(['R$', ' '], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) return null;
        $n = floatval($s);
        return $n >= 0 ? $n : null;
    }
    if (!is_numeric($v)) return null;
    $n = floatval($v);
    return $n >= 0 ? $n : null;
}

function correios_get_bearer_token_preco(): ?string {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strtolower((string)$k) === 'authorization') {
                    $raw = trim((string)$v);
                    if (stripos($raw, 'bearer ') === 0) return trim(substr($raw, 7));
                }
            }
        }
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $raw = trim((string)$_SERVER['HTTP_AUTHORIZATION']);
        if (stripos($raw, 'bearer ') === 0) return trim(substr($raw, 7));
    }
    if (defined('CORREIOS_CWS_JWT') && is_string(CORREIOS_CWS_JWT) && trim(CORREIOS_CWS_JWT) !== '') {
        $jwt = trim(CORREIOS_CWS_JWT);
        if (!correios_jwt_is_expired($jwt)) return $jwt;
    }
    // cws-* tokens are valid Bearer tokens directly — skip the JWT exchange round-trips.
    if (defined('CORREIOS_CWS_TOKEN') && is_string(CORREIOS_CWS_TOKEN) && CORREIOS_CWS_TOKEN !== '') {
        return CORREIOS_CWS_TOKEN;
    }
    return null;
}

function correios_get_jwt_preco_with_error(): array {
    if (defined('CORREIOS_CWS_JWT') && is_string(CORREIOS_CWS_JWT) && trim(CORREIOS_CWS_JWT) !== '') {
        $jwt = trim(CORREIOS_CWS_JWT);
        if (!correios_jwt_is_expired($jwt)) return ['jwt' => $jwt, 'error' => null];
    }
    $token = (defined('CORREIOS_CWS_TOKEN') && is_string(CORREIOS_CWS_TOKEN)) ? trim(CORREIOS_CWS_TOKEN) : '';
    if ($token === '') return ['jwt' => null, 'error' => 'CORREIOS_CWS_TOKEN ausente.'];

    $credentials = [];
    $user = (defined('CORREIOS_CWS_USER') && is_string(CORREIOS_CWS_USER)) ? trim(CORREIOS_CWS_USER) : '';
    if ($user !== '') {
        $credentials[] = base64_encode($user . ':' . $token);
    }
    $credentials[] = base64_encode($token . ':');
    $credentials[] = base64_encode(':' . $token);

    $lastErr = null;
    foreach ($credentials as $cred) {
        $tokenEndpoints = [
            ['url' => 'https://api.correios.com.br/token/v1/autentica', 'body' => null],
        ];

        $contrato = (defined('CORREIOS_CONTRATO') && is_string(CORREIOS_CONTRATO)) ? trim(CORREIOS_CONTRATO) : '';
        $dr = (defined('CORREIOS_DR') && is_string(CORREIOS_DR)) ? trim(CORREIOS_DR) : '';
        if ($contrato !== '') {
            $payload = ['contrato' => $contrato];
            if ($dr !== '' && is_numeric($dr)) $payload['dr'] = intval($dr);
            $tokenEndpoints[] = ['url' => 'https://api.correios.com.br/token/v1/autentica/contrato', 'body' => json_encode($payload)];
        }

        $cartao = (defined('CORREIOS_CARTAO') && is_string(CORREIOS_CARTAO)) ? trim(CORREIOS_CARTAO) : '';
        if ($cartao !== '') {
            $payload = ['numero' => $cartao];
            if ($contrato !== '') $payload['contrato'] = $contrato;
            if ($dr !== '' && is_numeric($dr)) $payload['dr'] = intval($dr);
            $tokenEndpoints[] = ['url' => 'https://api.correios.com.br/token/v1/autentica/cartaopostagem', 'body' => json_encode($payload)];
        }

        foreach ($tokenEndpoints as $ep) {
            $ch = curl_init($ep['url']);
            $headers = [
                'Authorization: Basic ' . $cred,
                'Accept: application/json',
            ];
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT        => 30,
            ];
            if ($ep['body'] !== null) {
                $headers[] = 'Content-Type: application/json';
                $opts[CURLOPT_HTTPHEADER] = $headers;
                $opts[CURLOPT_POSTFIELDS] = $ep['body'];
            }
            curl_setopt_array($ch, $opts);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $lastErr = 'Erro cURL ao autenticar: ' . $err;
                continue;
            }
            if ($code !== 200) {
                $lastErr = 'HTTP ' . $code . ' ao autenticar | body=' . (string)$resp;
                continue;
            }
            $data = json_decode($resp, true);
            $jwt  = $data['token'] ?? $data['access_token'] ?? $data['jwt'] ?? $data['accessToken'] ?? null;
            if ($jwt) return ['jwt' => $jwt, 'error' => null];
            $lastErr = 'Resposta de autenticação sem token válido.';
        }
    }

    // JWT exchange failed — try using the CWS token directly as a Bearer (works for cws-* tokens).
    if ($token !== '') return ['jwt' => $token, 'error' => null];

    if ($lastErr) return ['jwt' => null, 'error' => $lastErr];
    if ($user === '') return ['jwt' => null, 'error' => 'CORREIOS_CWS_USER ausente (muitos contratos exigem usuário + token no Basic).'];
    return ['jwt' => null, 'error' => 'Falha ao obter JWT.'];
}

function correios_preco_request(array $body, string $token): array {
    $coProduto = preg_replace('/\D/', '', (string)($body['coProduto'] ?? '03220'));
    if ($coProduto === '') throw new RuntimeException('coProduto inválido.');

    $cepOrigem = preg_replace('/\D/', '', (string)($body['cepOrigem'] ?? ''));
    $cepDestino = preg_replace('/\D/', '', (string)($body['cepDestino'] ?? ''));
    if (strlen($cepOrigem) !== 8) throw new RuntimeException('cepOrigem inválido.');
    if (strlen($cepDestino) !== 8) throw new RuntimeException('cepDestino inválido.');

    $pesoG = null;
    if (isset($body['psObjeto']) && is_numeric($body['psObjeto'])) $pesoG = (int) round(floatval($body['psObjeto']));
    if ($pesoG === null && isset($body['pesoGramas']) && is_numeric($body['pesoGramas'])) $pesoG = (int) round(floatval($body['pesoGramas']));
    if ($pesoG === null) $pesoG = correios_normalize_weight_to_grams($body['peso'] ?? null);
    if (!$pesoG || $pesoG <= 0) $pesoG = 300;

    $altura = isset($body['altura']) ? floatval($body['altura']) : 10.0;
    $largura = isset($body['largura']) ? floatval($body['largura']) : 15.0;
    $comprimento = isset($body['comprimento']) ? floatval($body['comprimento']) : 20.0;

    $alturaApi = max(1, (int)ceil(max(1.0, $altura)));
    $larguraApi = max(1, (int)ceil(max(1.0, $largura)));
    $comprimentoApi = max(1, (int)ceil(max(1.0, $comprimento)));

    $valorDeclarado = correios_normalize_brl_value($body['valorDeclarado'] ?? null);
    $valorDeclarado = max(0.0, floatval($valorDeclarado ?? 0.0));
    $vlDeclaradoStr = (string)max(0, (int)round($valorDeclarado));

    $contrato = (defined('CORREIOS_CONTRATO') && is_string(CORREIOS_CONTRATO)) ? trim(CORREIOS_CONTRATO) : '';
    $dr = (defined('CORREIOS_DR') && is_string(CORREIOS_DR)) ? trim(CORREIOS_DR) : '';
    $unidade = (defined('CORREIOS_UNIDADE') && is_string(CORREIOS_UNIDADE)) ? trim(CORREIOS_UNIDADE) : '';

    $contratoBody = isset($body['nuContrato']) ? trim((string)$body['nuContrato']) : '';
    $drBody = $body['nuDR'] ?? null;
    $unidadeBody = isset($body['nuUnidade']) ? trim((string)$body['nuUnidade']) : '';
    if ($contratoBody !== '') $contrato = $contratoBody;
    if ($drBody !== null && $drBody !== '' && is_numeric($drBody)) $dr = (string)intval($drBody);
    if ($unidadeBody !== '') $unidade = $unidadeBody;

    $tpObjeto = (string)($body['tpObjeto'] ?? '2');
    $diametro = (string)($body['diametro'] ?? '0');
    $psCubico = (string)($body['psCubico'] ?? '0');

    $criterios = [];
    if (isset($body['criterios']) && is_array($body['criterios'])) $criterios = $body['criterios'];

    $servicosAdicionais = [];
    if (isset($body['servicosAdicionais']) && is_array($body['servicosAdicionais'])) {
        $servicosAdicionais = array_values(array_map(fn($v) => (string)$v, $body['servicosAdicionais']));
    } elseif ($vlDeclaradoStr !== '0') {
        if ($coProduto === '03298') $servicosAdicionais = ['064'];
        else $servicosAdicionais = ['019'];
    }

    $produto = [
        'coProduto'    => $coProduto,
        'nuRequisicao' => (string)($body['nuRequisicao'] ?? '1'),
        'nuContrato'   => $contrato !== '' ? $contrato : null,
        'nuDR'         => ($dr !== '' && is_numeric($dr)) ? intval($dr) : null,
        'nuUnidade'    => $unidade !== '' ? $unidade : null,
        'cepOrigem'    => $cepOrigem,
        'cepDestino'   => $cepDestino,
        'psObjeto'     => (string)$pesoG,
        'tpObjeto'     => $tpObjeto,
        'comprimento'  => (string)$comprimentoApi,
        'largura'      => (string)$larguraApi,
        'altura'       => (string)$alturaApi,
        'diametro'     => $diametro,
        'psCubico'     => $psCubico,
        'servicosAdicionais' => $servicosAdicionais,
        'criterios'    => $criterios,
        'vlDeclarado'  => $vlDeclaradoStr,
    ];

    $produto = array_filter($produto, fn($v) => $v !== null);

    $payload = [
        'idLote' => (string)($body['idLote'] ?? ('LOTE_' . date('Ymd_His'))),
        'parametrosProduto' => [$produto],
    ];

    $ch = curl_init('https://api.correios.com.br/preco/v1/nacional');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) throw new RuntimeException('Erro cURL: ' . $err);
    if ($code < 200 || $code >= 300) throw new RuntimeException('HTTP ' . $code . ' | body=' . (string)$resp);

    $data = json_decode($resp, true);
    if (!is_array($data)) throw new RuntimeException('Resposta inválida.');

    $item = null;
    if (isset($data[0]) && is_array($data[0])) $item = $data[0];
    if (!$item && isset($data['parametrosProduto'][0]) && is_array($data['parametrosProduto'][0])) $item = $data['parametrosProduto'][0];
    if (!$item && isset($data['parametrosProdutoResposta'][0]) && is_array($data['parametrosProdutoResposta'][0])) $item = $data['parametrosProdutoResposta'][0];

    return [
        'request' => $payload,
        'response' => $data,
        'item' => $item,
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Método não permitido.']); exit; }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    $token = correios_get_bearer_token_preco();
    $jwtError = null;
    if (!$token) {
        $jwtTry = correios_get_jwt_preco_with_error();
        $token = $jwtTry['jwt'] ?? null;
        $jwtError = $jwtTry['error'] ?? null;
    }
    if (!$token) {
        http_response_code(502);
        echo json_encode(['error' => $jwtError ?: 'Falha ao autenticar no Correios.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        echo json_encode(correios_preco_request($body, $token), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(502);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
