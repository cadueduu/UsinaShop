<?php
require_once dirname(__DIR__) . '/includes/config.php';

function correios_jwt_is_expired(string $jwt): bool {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return true;
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!is_array($payload) || !isset($payload['exp'])) return false;
    return intval($payload['exp']) < time();
}

function correios_get_bearer_token_prazo(): ?string {
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

function correios_get_jwt_prazo_with_error(): array {
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

function correios_http_post_json(string $url, string $token, array $payload): array {
    $ch = curl_init($url);
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
    return ['resp' => $resp, 'code' => $code, 'err' => $err];
}

function correios_prazo_request(array $body, string $token): array {
    $coProduto = preg_replace('/\D/', '', (string)($body['coProduto'] ?? '03220'));
    if ($coProduto === '') throw new RuntimeException('coProduto inválido.');

    $cepOrigem = preg_replace('/\D/', '', (string)($body['cepOrigem'] ?? ''));
    $cepDestino = preg_replace('/\D/', '', (string)($body['cepDestino'] ?? ''));
    if (strlen($cepOrigem) !== 8) throw new RuntimeException('cepOrigem inválido.');
    if (strlen($cepDestino) !== 8) throw new RuntimeException('cepDestino inválido.');

    $dataPostagem = (string)($body['dataPostagem'] ?? '');
    if ($dataPostagem === '') $dataPostagem = date('Y-m-d');
    $dtEvento = (string)($body['dtEvento'] ?? '');
    if ($dtEvento === '') $dtEvento = date('d/m/Y');

    $contrato = (defined('CORREIOS_CONTRATO') && is_string(CORREIOS_CONTRATO)) ? trim(CORREIOS_CONTRATO) : '';
    $dr = (defined('CORREIOS_DR') && is_string(CORREIOS_DR)) ? trim(CORREIOS_DR) : '';
    $unidade = (defined('CORREIOS_UNIDADE') && is_string(CORREIOS_UNIDADE)) ? trim(CORREIOS_UNIDADE) : '';

    $contratoBody = isset($body['nuContrato']) ? trim((string)$body['nuContrato']) : '';
    $drBody = $body['nuDR'] ?? null;
    $unidadeBody = isset($body['nuUnidade']) ? trim((string)$body['nuUnidade']) : '';
    if ($contratoBody !== '') $contrato = $contratoBody;
    if ($drBody !== null && $drBody !== '' && is_numeric($drBody)) $dr = (string)intval($drBody);
    if ($unidadeBody !== '') $unidade = $unidadeBody;

    $prazoItem = [
        'coProduto' => $coProduto,
        'nuRequisicao' => (string)($body['nuRequisicao'] ?? '1'),
        'nuContrato' => $contrato !== '' ? $contrato : null,
        'nuDR' => ($dr !== '' && is_numeric($dr)) ? intval($dr) : null,
        'nuUnidade' => $unidade !== '' ? $unidade : null,
        'dtEvento' => $dtEvento,
        'cepOrigem' => $cepOrigem,
        'cepDestino' => $cepDestino,
        'dataPostagem' => $dataPostagem,
    ];
    $prazoItem = array_filter($prazoItem, fn($v) => $v !== null);

    $prazoPayload = [
        'idLote' => (string)($body['idLote'] ?? ('LOTE_' . date('Ymd_His'))),
        'parametrosPrazo' => [$prazoItem],
    ];

    $prazoTry = [
        'https://api.correios.com.br/prazo/v1/nacional',
        'https://api.correios.com.br/prazo/v3/v1/nacional',
    ];

    $prazoResp = null;
    $prazoCode = 0;
    $prazoErr = '';
    $prazoUrlUsed = null;
    foreach ($prazoTry as $u) {
        $r = correios_http_post_json($u, $token, $prazoPayload);
        $prazoResp = $r['resp'];
        $prazoCode = intval($r['code'] ?? 0);
        $prazoErr = (string)($r['err'] ?? '');
        $prazoUrlUsed = $u;
        if ($prazoErr === '' && $prazoCode >= 200 && $prazoCode < 300) break;
    }

    if ($prazoErr) throw new RuntimeException('Erro cURL: ' . $prazoErr);
    if ($prazoCode < 200 || $prazoCode >= 300) throw new RuntimeException('HTTP ' . $prazoCode . ' | body=' . (string)$prazoResp);

    $prazoData = json_decode($prazoResp, true);
    if (!is_array($prazoData)) throw new RuntimeException('Resposta inválida.');

    $item = null;
    if (isset($prazoData[0]) && is_array($prazoData[0])) $item = $prazoData[0];
    if (!$item && isset($prazoData['parametrosPrazo'][0]) && is_array($prazoData['parametrosPrazo'][0])) $item = $prazoData['parametrosPrazo'][0];

    $prazoEntrega = null;
    if (is_array($item) && isset($item['prazoEntrega']) && is_numeric($item['prazoEntrega'])) $prazoEntrega = intval($item['prazoEntrega']);

    $dataPrevistaData = null;
    $dataPrevistaUrlUsed = null;
    if ($prazoEntrega !== null) {
        $dtParts = explode('-', $dataPostagem);
        if (count($dtParts) === 3) {
            $dtPostagem = $dtParts[2] . '-' . $dtParts[1] . '-' . $dtParts[0];
        } else {
            $dtPostagem = date('d-m-Y');
        }

        $query = [
            'cepOrigem' => $cepOrigem,
            'cepDestino' => $cepDestino,
            'prazo' => $prazoEntrega,
            'dtPostagem' => $dtPostagem,
        ];
        if (array_key_exists('isSabadoDiaUtil', $body)) $query['isSabadoDiaUtil'] = $body['isSabadoDiaUtil'] ? 'true' : 'false';
        if (array_key_exists('isDomingoDiaUtil', $body)) $query['isDomingoDiaUtil'] = $body['isDomingoDiaUtil'] ? 'true' : 'false';

        $dpTry = [
            'https://api.correios.com.br/prazo/v1/data-prevista?' . http_build_query($query),
            'https://api.correios.com.br/prazo/v3/v1/data-prevista?' . http_build_query($query),
        ];
        foreach ($dpTry as $url) {
            $ch2 = curl_init($url);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'accept: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $dpResp = curl_exec($ch2);
            $dpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $dpErr  = curl_error($ch2);
            curl_close($ch2);
            $dataPrevistaUrlUsed = $url;
            if ($dpErr) continue;
            if ($dpCode < 200 || $dpCode >= 300) continue;
            $dpData = json_decode($dpResp, true);
            if (is_array($dpData)) { $dataPrevistaData = $dpData; break; }
        }
    }

    return [
        'requestPrazo' => $prazoPayload,
        'prazoUrl' => $prazoUrlUsed,
        'responsePrazo' => $prazoData,
        'prazoItem' => $item,
        'prazoEntrega' => $prazoEntrega,
        'dataPrevistaUrl' => $dataPrevistaUrlUsed,
        'responseDataPrevista' => $dataPrevistaData,
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

    $token = correios_get_bearer_token_prazo();
    $jwtError = null;
    if (!$token) {
        $jwtTry = correios_get_jwt_prazo_with_error();
        $token = $jwtTry['jwt'] ?? null;
        $jwtError = $jwtTry['error'] ?? null;
    }
    if (!$token) {
        http_response_code(502);
        echo json_encode(['error' => $jwtError ?: 'Falha ao autenticar no Correios.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        echo json_encode(correios_prazo_request($body, $token), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(502);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
