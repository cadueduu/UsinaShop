<?php
/**
 * Unified freight quote endpoint.
 *
 * Single POST replaces the four separate correios-preco / correios-data-entrega calls.
 * Batches both service codes into one preco request and one prazo request, then fires
 * them in parallel via curl_multi. Total Correios latency = max(preco_ms, prazo_ms)
 * instead of sum(4 × serial_ms).
 *
 * Request body (JSON):
 *   cepDestino    string  8-digit destination CEP (required)
 *   cepOrigem     string  8-digit origin CEP (optional, falls back to CEP_ORIGEM env)
 *   peso          float   weight in kg (default 0.3)
 *   altura        float   height in cm (default 10)
 *   largura       float   width in cm  (default 15)
 *   comprimento   float   length in cm (default 20)
 *   valorDeclarado float  declared value in BRL (default 0, disables insurance)
 *
 * Response (JSON array, one entry per available service):
 *   [ { "codigo": "03298", "nome": "SEDEX", "preco": 25.40, "prazo": 1 }, ... ]
 */

require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (defined('APP_BASE_URL') && APP_BASE_URL !== '') {
    header('Access-Control-Allow-Origin: ' . APP_BASE_URL);
} else {
    // Same-origin only in production; wildcard only if no base URL is configured.
    header('Access-Control-Allow-Origin: *');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

// ─── Token resolution ─────────────────────────────────────────────────────────
// Priority: valid pre-set JWT > direct CWS token (no exchange round-trip needed).
function frete_resolve_token(): ?string {
    if (defined('CORREIOS_CWS_JWT') && is_string(CORREIOS_CWS_JWT) && CORREIOS_CWS_JWT !== '') {
        $jwt = trim(CORREIOS_CWS_JWT);
        $parts = explode('.', $jwt);
        $expired = true;
        if (count($parts) >= 2) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $expired = !is_array($payload) || !isset($payload['exp']) || intval($payload['exp']) < time();
        }
        if (!$expired) return $jwt;
    }
    if (defined('CORREIOS_CWS_TOKEN') && is_string(CORREIOS_CWS_TOKEN) && CORREIOS_CWS_TOKEN !== '') {
        return CORREIOS_CWS_TOKEN;
    }
    return null;
}

// ─── Input parsing ────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

$cepOrigem  = preg_replace('/\D/', '', (string)($body['cepOrigem']  ?? (defined('CEP_ORIGEM') ? CEP_ORIGEM : '')));
$cepDestino = preg_replace('/\D/', '', (string)($body['cepDestino'] ?? ''));

if (strlen($cepOrigem) !== 8) {
    http_response_code(422);
    echo json_encode(['error' => 'CEP de origem inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strlen($cepDestino) !== 8) {
    http_response_code(422);
    echo json_encode(['error' => 'CEP de destino inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = frete_resolve_token();
if (!$token) {
    http_response_code(502);
    echo json_encode(['error' => 'Configure CORREIOS_CWS_TOKEN no .env.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pesoG_in = null;
if (isset($body['pesoGramas']) && is_numeric($body['pesoGramas'])) {
    $pesoG_in = (int) round(floatval($body['pesoGramas']));
} elseif (isset($body['peso_g']) && is_numeric($body['peso_g'])) {
    $pesoG_in = (int) round(floatval($body['peso_g']));
}
$pesoG = $pesoG_in !== null
       ? max(300, $pesoG_in)
       : max(300, (int) round(floatval($body['peso'] ?? 0.3) * 1000));
$altura      = max(1, (int) ceil(floatval($body['altura']         ?? 10)));
$largura     = max(1, (int) ceil(floatval($body['largura']        ?? 15)));
$comprimento = max(1, (int) ceil(floatval($body['comprimento']    ?? 20)));
$vlDeclarado = (string) max(0, (int) round(floatval($body['valorDeclarado'] ?? 0)));

$contrato = (defined('CORREIOS_CONTRATO') && CORREIOS_CONTRATO !== '') ? CORREIOS_CONTRATO : '';
$dr       = (defined('CORREIOS_DR')       && CORREIOS_DR       !== '') ? (int) CORREIOS_DR   : null;
$unidade  = (defined('CORREIOS_UNIDADE')  && CORREIOS_UNIDADE  !== '') ? CORREIOS_UNIDADE   : '';

$servicos = ['03298', '03220'];
$idLote   = 'LOTE_' . date('Ymd_His');

// ─── Build batch payloads ─────────────────────────────────────────────────────
$precoItems = [];
$prazoItems = [];
foreach ($servicos as $i => $code) {
    $req = (string)($i + 1);

    $svcAdicionais = ($vlDeclarado !== '0') ? ($code === '03298' ? ['064'] : ['019']) : [];

    $precoItem = [
        'coProduto'          => $code,
        'nuRequisicao'       => $req,
        'cepOrigem'          => $cepOrigem,
        'cepDestino'         => $cepDestino,
        'psObjeto'           => (string) $pesoG,
        'tpObjeto'           => '2',
        'comprimento'        => (string) $comprimento,
        'largura'            => (string) $largura,
        'altura'             => (string) $altura,
        'diametro'           => '0',
        'psCubico'           => '0',
        'servicosAdicionais' => $svcAdicionais,
        'criterios'          => [],
        'vlDeclarado'        => $vlDeclarado,
    ];
    if ($contrato !== '') $precoItem['nuContrato'] = $contrato;
    if ($dr !== null)     $precoItem['nuDR']       = $dr;
    if ($unidade !== '')  $precoItem['nuUnidade']  = $unidade;
    $precoItems[] = $precoItem;

    $prazoItem = [
        'coProduto'    => $code,
        'nuRequisicao' => $req,
        'cepOrigem'    => $cepOrigem,
        'cepDestino'   => $cepDestino,
        'dataPostagem' => date('Y-m-d'),
        'dtEvento'     => date('d/m/Y'),
    ];
    if ($contrato !== '') $prazoItem['nuContrato'] = $contrato;
    if ($dr !== null)     $prazoItem['nuDR']       = $dr;
    $prazoItems[] = $prazoItem;
}

$precoPayload = ['idLote' => $idLote, 'parametrosProduto' => $precoItems];
$prazoPayload = ['idLote' => $idLote, 'parametrosPrazo'   => $prazoItems];

// ─── Fire both requests in parallel ──────────────────────────────────────────
$authHeader = 'Authorization: Bearer ' . $token;

$mh = curl_multi_init();

$chPreco = curl_init('https://api.correios.com.br/preco/v1/nacional');
curl_setopt_array($chPreco, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => json_encode($precoPayload),
    CURLOPT_HTTPHEADER     => ['accept: application/json', 'Content-Type: application/json', $authHeader],
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
]);

$chPrazo = curl_init('https://api.correios.com.br/prazo/v1/nacional');
curl_setopt_array($chPrazo, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => json_encode($prazoPayload),
    CURLOPT_HTTPHEADER     => ['accept: application/json', 'Content-Type: application/json', $authHeader],
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
]);

curl_multi_add_handle($mh, $chPreco);
curl_multi_add_handle($mh, $chPrazo);

do {
    $status = curl_multi_exec($mh, $running);
    if ($running) curl_multi_select($mh, 0.5);
} while ($running && $status === CURLM_OK);

$precoBody = (string) curl_multi_getcontent($chPreco);
$precoCode = (int) curl_getinfo($chPreco, CURLINFO_HTTP_CODE);
$prazoBody = (string) curl_multi_getcontent($chPrazo);
$prazoCode = (int) curl_getinfo($chPrazo, CURLINFO_HTTP_CODE);

curl_multi_remove_handle($mh, $chPreco);
curl_multi_remove_handle($mh, $chPrazo);
curl_close($chPreco);
curl_close($chPrazo);
curl_multi_close($mh);

if ($precoCode < 200 || $precoCode >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'Erro ao consultar preços (HTTP ' . $precoCode . ').'], JSON_UNESCAPED_UNICODE);
    exit;
}

$precoData = json_decode($precoBody, true);
$prazoData = ($prazoCode >= 200 && $prazoCode < 300) ? json_decode($prazoBody, true) : null;

if (!is_array($precoData)) {
    http_response_code(502);
    echo json_encode(['error' => 'Resposta de preço inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Index responses by coProduto ─────────────────────────────────────────────
$precoIndex = [];
foreach ($precoData as $item) {
    if (is_array($item) && isset($item['coProduto'])) {
        $precoIndex[(string) $item['coProduto']] = $item;
    }
}

$prazoIndex = [];
if (is_array($prazoData)) {
    foreach ($prazoData as $item) {
        if (is_array($item) && isset($item['coProduto'])) {
            $prazoIndex[(string) $item['coProduto']] = $item;
        }
    }
}

// ─── Merge and emit result ────────────────────────────────────────────────────
$result = [];
foreach ($servicos as $code) {
    $pi = $precoIndex[$code] ?? null;
    if (!$pi) continue;
    if (!empty($pi['txErro'])) continue;

    $rawPreco = $pi['pcTotal'] ?? $pi['pcFinal'] ?? $pi['pcProduto'] ?? $pi['pcBase'] ?? null;
    if ($rawPreco === null) continue;
    $s = trim(str_replace(['R$', ' '], '', (string) $rawPreco));
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, ',')) {
        $s = str_replace(',', '.', $s);
    }
    $preco = floatval($s);
    if ($preco <= 0) continue;

    $zi    = $prazoIndex[$code] ?? null;
    $prazo = ($zi && isset($zi['prazoEntrega']) && is_numeric($zi['prazoEntrega']))
           ? intval($zi['prazoEntrega'])
           : null;

    // noProduto is absent from batch preco responses — use a static fallback map.
    static $serviceNames = ['03298' => 'PAC', '03220' => 'SEDEX'];
    $nome = $pi['noProduto'] ?? ($serviceNames[$code] ?? $code);

    $result[] = [
        'codigo' => $code,
        'nome'   => $nome,
        'preco'  => round($preco, 2),
        'prazo'  => $prazo,
        'cep_origem' => $cepOrigem,
        'cep_destino' => $cepDestino,
        'peso_g' => $pesoG,
        'peso_kg' => round($pesoG / 1000, 3),
        'altura' => $altura,
        'largura' => $largura,
        'comprimento' => $comprimento,
        'valor_declarado' => (int)$vlDeclarado,
    ];
}

if (empty($result)) {
    http_response_code(422);
    echo json_encode(['error' => 'Nenhum serviço disponível para esta rota.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
