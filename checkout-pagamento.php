<?php
require_once __DIR__ . '/includes/config.php';

$action = (string)($_GET['action'] ?? '');
if ($action === 'product-images' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    if (LOCAL_DATA_MODE) {
        echo json_encode(['ok' => true, 'images' => (object)[]], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!defined('SUPABASE_URL') || SUPABASE_URL === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Supabase não configurado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idsRaw = trim((string)($_GET['ids'] ?? ''));
    $ids = array_filter(array_map('trim', explode(',', $idsRaw)), fn($v) => $v !== '' && ctype_digit($v));
    $ids = array_values(array_unique($ids));
    $ids = array_slice($ids, 0, 50);

    $out = [];
    foreach ($ids as $id) {
        $imgs = product_images_low($id);
        // Cart-sidebar thumbs render at ~60px; ask Supabase for 120 to cover retina.
        $out[$id] = !empty($imgs) ? product_image_render_url($imgs[0], 120, 55, 'webp') : '';
    }
    echo json_encode(['ok' => true, 'images' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'mp-methods' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    if (!defined('MP_ACCESS_TOKEN') || MP_ACCESS_TOKEN === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Mercado Pago não configurado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ch = curl_init('https://api.mercadopago.com/v1/payment_methods');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Falha ao consultar métodos de pagamento.', 'detail' => $err], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Resposta inválida do Mercado Pago.', 'http_status' => $http], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ids = [];
    $types = [];
    foreach ($data as $m) {
        if (!is_array($m)) continue;
        if (isset($m['id'])) $ids[] = (string)$m['id'];
        if (isset($m['payment_type_id'])) $types[] = (string)$m['payment_type_id'];
    }
    $ids = array_values(array_unique($ids));
    $types = array_values(array_unique($types));

    echo json_encode([
        'ok' => true,
        'http_status' => $http,
        'has_pix' => in_array('pix', $ids, true),
        'has_bank_transfer' => in_array('bank_transfer', $types, true),
        'payment_type_ids' => $types,
        'payment_method_ids' => $ids,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'mp-merchant-order' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    if (!defined('MP_ACCESS_TOKEN') || MP_ACCESS_TOKEN === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Mercado Pago não configurado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $preferenceId = trim((string)($_GET['preference_id'] ?? ''));
    if ($preferenceId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'preference_id obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $url = 'https://api.mercadopago.com/merchant_orders?preference_id=' . rawurlencode($preferenceId);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Falha ao consultar merchant_orders.', 'detail' => $err], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Resposta inválida do Mercado Pago.', 'http_status' => $http, 'body' => substr((string)$raw, 0, 500)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payments = [];
    $elements = $data['elements'] ?? [];
    if (is_array($elements) && isset($elements[0]) && is_array($elements[0])) {
        $p = $elements[0]['payments'] ?? [];
        if (is_array($p)) {
            foreach ($p as $pay) {
                if (!is_array($pay)) continue;
                $payments[] = [
                    'id' => $pay['id'] ?? null,
                    'status' => $pay['status'] ?? null,
                    'status_detail' => $pay['status_detail'] ?? null,
                    'payment_type' => $pay['payment_type'] ?? null,
                ];
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'http_status' => $http,
        'preference_id' => $preferenceId,
        'payments' => $payments,
        'raw' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'mp-webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!defined('MP_ACCESS_TOKEN') || MP_ACCESS_TOKEN === '') {
        http_response_code(200);
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        http_response_code(200);
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Refuse to process webhooks in local mode — sb() would write to the local
    // JSON file instead of Supabase, creating orphaned records and masking failures.
    if (LOCAL_DATA_MODE) {
        http_response_code(200);
        echo json_encode(['ok' => false, 'ignored' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!defined('MP_WEBHOOK_SECRET') || MP_WEBHOOK_SECRET === '') {
        http_response_code(200);
        echo json_encode(['ok' => true, 'ignored' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $xSignature = (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '');
    $xRequestId = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    $dataId = (string)($_GET['data_id'] ?? ($_GET['data.id'] ?? ''));
    if ($dataId === '') {
        $dataId = (string)($_GET['id'] ?? '');
    }

    $ts = '';
    $v1 = '';
    if ($xSignature !== '') {
        $parts = explode(',', $xSignature);
        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) continue;
            $k = trim($kv[0]);
            $val = trim($kv[1]);
            if ($k === 'ts') $ts = $val;
            if ($k === 'v1') $v1 = $val;
        }
    }

    $manifest = '';
    if ($dataId !== '') $manifest .= 'id:' . $dataId . ';';
    if ($xRequestId !== '') $manifest .= 'request-id:' . $xRequestId . ';';
    if ($ts !== '') $manifest .= 'ts:' . $ts . ';';

    if ($manifest === '' || $v1 === '') {
        http_response_code(401);
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $calc = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
    if (!hash_equals($calc, $v1)) {
        http_response_code(401);
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ts !== '' && ctype_digit($ts)) {
        $nowMs = (int)floor(microtime(true) * 1000);
        $tsMs = (int)$ts;
        $driftMs = abs($nowMs - $tsMs);
        if ($driftMs > 10 * 60 * 1000) {
            http_response_code(401);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) $payload = [];
    $type = (string)($payload['type'] ?? ($_GET['type'] ?? ''));
    $bodyDataId = '';
    if (is_array($payload['data'] ?? null) && isset($payload['data']['id'])) {
        $bodyDataId = (string)$payload['data']['id'];
    }
    $paymentId = $bodyDataId !== '' ? $bodyDataId : $dataId;

    if ($type !== 'payment' || $paymentId === '' || !ctype_digit($paymentId)) {
        http_response_code(200);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ch = curl_init('https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code < 200 || $code >= 300) {
        http_response_code(200);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode((string)$resp, true);
    $status = is_array($data) ? (string)($data['status'] ?? '') : '';
    $ref = is_array($data) ? (string)($data['external_reference'] ?? '') : '';
    $pedidoId = ($ref !== '' && ctype_digit($ref)) ? intval($ref) : 0;

    if ($pedidoId > 0) {
        if ($status === 'approved') {
            sb('pedido', ['id' => 'eq.' . $pedidoId], 'PATCH', ['status' => 'pago'], SUPABASE_SERVICE_KEY);
        } elseif (in_array($status, ['rejected', 'cancelled'], true)) {
            sb('pedido', ['id' => 'eq.' . $pedidoId], 'PATCH', ['status' => 'cancelado'], SUPABASE_SERVICE_KEY);
        }
    }

    http_response_code(200);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'mp-preference' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // ── Preconditions ────────────────────────────────────────────────────────
    if (!defined('MP_ACCESS_TOKEN') || MP_ACCESS_TOKEN === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Mercado Pago não configurado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Servidor sem chave de serviço do Supabase.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (LOCAL_DATA_MODE) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Pagamento online indisponível no modo local.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Validate Supabase JWT ─────────────────────────────────────────────────
    $authHeader  = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $clientToken = '';
    if (stripos($authHeader, 'bearer ') === 0) {
        $clientToken = trim(substr($authHeader, 7));
    }
    if ($clientToken === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Autenticação necessária.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $authCh = curl_init(SUPABASE_URL . '/auth/v1/user');
    curl_setopt_array($authCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $clientToken,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $authResp = curl_exec($authCh);
    $authCode = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
    curl_close($authCh);
    if ($authCode !== 200) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Sessão inválida. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $authUser  = json_decode((string)$authResp, true);
    $clienteId = (string)($authUser['id'] ?? '');
    if ($clienteId === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Usuário não identificado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Parse and validate request body ──────────────────────────────────────
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    $enderecoIdReq = trim((string)($body['endereco_id'] ?? ''));
    $shippingCode  = preg_replace('/\D/', '', (string)($body['shipping_code'] ?? ''));

    if ($enderecoIdReq === '' || !is_numeric($enderecoIdReq) || intval($enderecoIdReq) <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'endereco_id inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($shippingCode, ['03298', '03220'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Código de frete inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $enderecoIdInt = intval($enderecoIdReq);

    try {
        // ── Validate address ownership ────────────────────────────────────────
        $enderecoRows = sb('endereco', [
            'id'         => 'eq.' . $enderecoIdInt,
            'cliente_id' => 'eq.' . $clienteId,
            'select'     => 'id,cep',
        ], 'GET', null, SUPABASE_SERVICE_KEY);
        if (empty($enderecoRows)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Endereço não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $cepDestino = preg_replace('/\D/', '', (string)($enderecoRows[0]['cep'] ?? ''));
        if (strlen($cepDestino) !== 8) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'CEP do endereço inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ── Read cart from database ───────────────────────────────────────────
        $cartRows = sb('carrinho', [
            'cliente_id' => 'eq.' . $clienteId,
            'select'     => 'codprod,quantidade',
        ], 'GET', null, SUPABASE_SERVICE_KEY);
        if (empty($cartRows)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Carrinho vazio.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ids = array_values(array_unique(array_map(fn($r) => intval($r['codprod'] ?? 0), $cartRows)));
        $ids = array_values(array_filter($ids, fn($v) => $v > 0));
        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Itens de carrinho inválidos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $in = 'in.(' . implode(',', $ids) . ')';

        // ── Fetch current prices, stock, and dimensions from database ─────────
        $batchData = sb_multi([
            'preco'   => ['preco',   ['codprod' => $in, 'select' => 'codprod,vlr_venda'],          'GET', null, SUPABASE_SERVICE_KEY],
            'estoque' => ['estoque', ['codprod' => $in, 'select' => 'codprod,estoque_disponivel'], 'GET', null, SUPABASE_SERVICE_KEY],
            'produto' => ['produto', ['codprod' => $in, 'select' => 'codprod,comnome,descrprod,peso,altura,largura,comprimento'], 'GET', null, SUPABASE_SERVICE_KEY],
        ]);

        $precoMap   = [];
        foreach ($batchData['preco']   ?? [] as $r) { $precoMap[intval($r['codprod'])]   = floatval($r['vlr_venda']           ?? 0); }
        $estoqueMap = [];
        foreach ($batchData['estoque'] ?? [] as $r) { $estoqueMap[intval($r['codprod'])] = floatval($r['estoque_disponivel']   ?? 0); }
        $produtoMap = [];
        foreach ($batchData['produto'] ?? [] as $r) { $produtoMap[intval($r['codprod'])] = $r; }

        // ── Validate each item and compute order totals ───────────────────────
        $mpItems        = [];
        $pedidoItens    = [];
        $pesoGramas     = 0;
        $maxAltura      = 1;
        $maxLargura     = 1;
        $maxComprimento = 1;
        $subtotal       = 0.0;

        foreach ($cartRows as $cartRow) {
            $pid  = intval($cartRow['codprod']   ?? 0);
            $qty  = intval($cartRow['quantidade'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;

            $price = $precoMap[$pid]   ?? 0.0;
            $stock = $estoqueMap[$pid] ?? 0.0;
            $prod  = $produtoMap[$pid] ?? null;

            if ($price <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Produto sem preço disponível no momento.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($stock < $qty) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Estoque insuficiente para um ou mais produtos.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $title = '';
            if (is_array($prod)) {
                $title = trim((string)($prod['comnome']   ?? ''));
                if ($title === '') $title = trim((string)($prod['descrprod'] ?? ''));
            }
            if ($title === '') $title = 'Produto #' . $pid;

            $pesoUnit   = floatval(is_array($prod) ? ($prod['peso']        ?? 0.3)  : 0.3);
            $alt        = floatval(is_array($prod) ? ($prod['altura']      ?? 10.0) : 10.0);
            $larg       = floatval(is_array($prod) ? ($prod['largura']     ?? 15.0) : 15.0);
            $comp       = floatval(is_array($prod) ? ($prod['comprimento'] ?? 20.0) : 20.0);

            $pesoGramas     += max(1, (int)round($pesoUnit * $qty * 1000));
            $maxAltura       = max($maxAltura,      (int)ceil(max(1.0, $alt)));
            $maxLargura      = max($maxLargura,     (int)ceil(max(1.0, $larg)));
            $maxComprimento  = max($maxComprimento, (int)ceil(max(1.0, $comp)));

            $subtotal += $price * $qty;

            $mpItems[] = [
                'title'       => $title,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'currency_id' => 'BRL',
            ];
            $pedidoItens[] = [
                'codprod'      => $pid,
                'quantidade'   => $qty,
                'vlr_unitario' => $price,
            ];
        }

        if (empty($mpItems)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nenhum item válido no carrinho.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $pesoGramas = max(300, $pesoGramas);

        // ── Calculate shipping server-side via Correios ───────────────────────
        require_once __DIR__ . '/api/correios-preco.php';
        $jwtResult = correios_get_jwt_preco_with_error();
        if ($jwtResult['jwt'] === null) {
            error_log('[Checkout] Correios JWT error: ' . ($jwtResult['error'] ?? ''));
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'Erro ao calcular frete. Tente novamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $cepOrigem = preg_replace('/\D/', '', CEP_ORIGEM);
        $freteResult = correios_preco_request([
            'coProduto'      => $shippingCode,
            'cepOrigem'      => $cepOrigem,
            'cepDestino'     => $cepDestino,
            'pesoGramas'     => $pesoGramas,
            'altura'         => $maxAltura,
            'largura'        => $maxLargura,
            'comprimento'    => $maxComprimento,
            'valorDeclarado' => $subtotal,
        ], $jwtResult['jwt']);
        $freteItem = $freteResult['item'] ?? null;
        $freteVal  = null;
        if (is_array($freteItem)) {
            $raw = $freteItem['pcTotal'] ?? $freteItem['pcFinal'] ?? $freteItem['pcServico'] ?? $freteItem['pcBase'] ?? null;
            if ($raw !== null) {
                $n = floatval(str_replace(',', '.', (string)$raw));
                if (is_finite($n) && $n > 0) $freteVal = $n;
            }
        }
        if ($freteVal === null || $freteVal <= 0) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'Não foi possível calcular o frete. Verifique o CEP e tente novamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $totalGeral = round($subtotal + $freteVal, 2);

        // ── Resolve base URL ──────────────────────────────────────────────────
        $baseUrl = defined('APP_BASE_URL') ? APP_BASE_URL : '';
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = (string)($_SERVER['HTTP_HOST'] ?? '');
            if ($host === '') {
                $sName = (string)($_SERVER['SERVER_NAME'] ?? '');
                $sPort = (string)($_SERVER['SERVER_PORT'] ?? '');
                if ($sName !== '') {
                    $isDefault = ($scheme === 'https' && $sPort === '443') || ($scheme === 'http' && $sPort === '80') || $sPort === '';
                    $host = $isDefault ? $sName : ($sName . ':' . $sPort);
                }
            }
            if ($host !== '') $baseUrl = $scheme . '://' . $host;
        }
        if ($baseUrl === '') {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Base URL não configurada. Defina APP_BASE_URL no servidor.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $hostCheck = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?? ''));
        if ($hostCheck === '' || in_array($hostCheck, ['localhost', '127.0.0.1', '::1'], true)) {
            http_response_code(500);
            echo json_encode([
                'ok'    => false,
                'error' => 'O Mercado Pago exige URLs públicas (não aceita localhost). Configure APP_BASE_URL com um domínio HTTPS público.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ── Create pedido in database ─────────────────────────────────────────
        $pedidoInsert = sb('pedido', [], 'POST', [
            'cliente_id'       => $clienteId,
            'endereco_id'      => $enderecoIdInt,
            'status'           => 'pendente',
            'metodo_pagamento' => 'mercadopago',
            'vlr_frete'        => round($freteVal, 2),
            'vlr_total'        => $totalGeral,
        ], SUPABASE_SERVICE_KEY);

        $pedidoId = null;
        if (!empty($pedidoInsert) && isset($pedidoInsert[0]['id'])) {
            $pedidoId = $pedidoInsert[0]['id'];
        } elseif (!empty($pedidoInsert) && isset($pedidoInsert['id'])) {
            $pedidoId = $pedidoInsert['id'];
        }
        if (!$pedidoId) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Erro ao registrar pedido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ── Create pedido_item rows ───────────────────────────────────────────
        $itensToInsert = array_map(fn($it) => array_merge($it, ['pedido_id' => $pedidoId]), $pedidoItens);
        $itensResult   = sb('pedido_item', [], 'POST', $itensToInsert, SUPABASE_SERVICE_KEY);
        if (empty($itensResult)) {
            sb('pedido', ['id' => 'eq.' . $pedidoId], 'DELETE', null, SUPABASE_SERVICE_KEY);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Erro ao registrar itens do pedido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ── Create Mercado Pago Preference ────────────────────────────────────
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            sb('pedido_item', ['pedido_id' => 'eq.' . $pedidoId], 'DELETE', null, SUPABASE_SERVICE_KEY);
            sb('pedido',      ['id'        => 'eq.' . $pedidoId], 'DELETE', null, SUPABASE_SERVICE_KEY);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Dependências PHP ausentes. Execute: composer install'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        require_once $autoload;

        $mpHasPix = false;
        $chPix = curl_init('https://api.mercadopago.com/v1/payment_methods');
        curl_setopt_array($chPix, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $rawPix  = curl_exec($chPix);
        $httpPix = curl_getinfo($chPix, CURLINFO_HTTP_CODE);
        curl_close($chPix);
        if ($rawPix !== false && $httpPix >= 200 && $httpPix < 300) {
            $methods = json_decode((string)$rawPix, true);
            if (is_array($methods)) {
                foreach ($methods as $m) {
                    if (is_array($m) && (($m['id'] ?? '') === 'pix')) { $mpHasPix = true; break; }
                }
            }
        }

        $excludedPaymentTypes = [
            ['id' => 'ticket'],
            ['id' => 'atm'],
            ['id' => 'debit_card'],
            ['id' => 'prepaid_card'],
        ];
        if (!$mpHasPix) $excludedPaymentTypes[] = ['id' => 'bank_transfer'];

        $successUrl = $baseUrl . '/aprovado';
        $returnUrl  = $baseUrl . '/conta.php';
        $mpRequest  = [
            'external_reference' => (string)$pedidoId,
            'items'              => $mpItems,
            'shipments'          => ['cost' => max(0, $freteVal), 'mode' => 'not_specified'],
            'payment_methods'    => ['excluded_payment_types' => $excludedPaymentTypes],
            'notification_url'   => str_starts_with($baseUrl, 'https://') ? ($baseUrl . '/checkout-pagamento.php?action=mp-webhook') : null,
            'back_urls'          => ['success' => $successUrl, 'pending' => $returnUrl, 'failure' => $returnUrl],
            'auto_return'        => 'approved',
        ];
        if ($mpRequest['notification_url'] === null) unset($mpRequest['notification_url']);

        \MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
        $runtimeEnv = (defined('MP_RUNTIME_ENV') && MP_RUNTIME_ENV === 'LOCAL')
            ? \MercadoPago\MercadoPagoConfig::LOCAL
            : \MercadoPago\MercadoPagoConfig::SERVER;
        \MercadoPago\MercadoPagoConfig::setRuntimeEnviroment($runtimeEnv);

        $client     = new \MercadoPago\Client\Preference\PreferenceClient();
        $preference = $client->create($mpRequest);
        $isLive     = $preference->live_mode ?? null;
        $initPoint  = ($isLive === false)
            ? ($preference->sandbox_init_point ?? null)
            : (($isLive === true)
                ? ($preference->init_point ?? null)
                : ($preference->sandbox_init_point ?? $preference->init_point ?? null));

        if (!$initPoint) {
            sb('pedido_item', ['pedido_id' => 'eq.' . $pedidoId], 'DELETE', null, SUPABASE_SERVICE_KEY);
            sb('pedido',      ['id'        => 'eq.' . $pedidoId], 'DELETE', null, SUPABASE_SERVICE_KEY);
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'Mercado Pago não retornou link de pagamento.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Persist preference_id and clear cart server-side
        sb('pedido',   ['id' => 'eq.' . $pedidoId],         'PATCH',  ['mp_preference_id' => $preference->id ?? null], SUPABASE_SERVICE_KEY);
        sb('carrinho', ['cliente_id' => 'eq.' . $clienteId], 'DELETE', null, SUPABASE_SERVICE_KEY);

        echo json_encode([
            'ok'            => true,
            'init_point'    => $initPoint,
            'pedido_id'     => $pedidoId,
            'preference_id' => $preference->id ?? null,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (\MercadoPago\Exceptions\MPApiException $e) {
        $status  = $e->getApiResponse() ? $e->getApiResponse()->getStatusCode() : null;
        $content = $e->getApiResponse() ? $e->getApiResponse()->getContent() : null;
        error_log('[MercadoPago] MPApiException status=' . (string)$status . ' content=' . json_encode($content, JSON_UNESCAPED_UNICODE));
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Erro ao processar pagamento. Tente novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('[Checkout] Exception: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Erro interno. Tente novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$endereco_id = $_GET['endereco'] ?? '';
if (empty($endereco_id)) {
    header('Location: /checkout.php');
    exit;
}

$page_title = 'Revisão do Pedido';
$categories = fetch_categories(false);
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>
<main class="page-content" style="background:#f9fafb; min-height:calc(100vh - 96px); padding-top:2rem; padding-bottom:4rem;">
  <div class="container" style="max-width: 60rem; margin: 0 auto;">
    
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.875rem; font-weight: 700; color: #111827; border-left: 4px solid #facc15; padding-left: 0.75rem;">Revisão do Pedido</h1>
      <p style="color: #6b7280; margin-top: 0.5rem; padding-left: 1rem;">Confira os dados e siga para o Mercado Pago.</p>
    </div>

    <div style="display: flex; flex-direction: column; gap: 2rem; margin-top: 2rem;" class="checkout-grid">
      
      <div style="flex: 1; background: #fff; padding: 2rem; border-radius: 1rem; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="display:flex;flex-direction:column;gap:.75rem;">
          <h2 style="font-size:1.125rem;font-weight:700;color:#111827;margin:0;">Pagamento</h2>
          <div style="color:#6b7280;font-size:.875rem;line-height:1.4;">
            Ao confirmar, você será redirecionado para o Mercado Pago para concluir o pagamento com segurança.
            Após a aprovação, você retornará para o site e seu pedido será finalizado.
          </div>
        </div>
      </div>

      <!-- Resumo do Pedido -->
      <div style="width: 100%; max-width: 320px; shrink: 0;">
        <div style="background: #fff; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; top: 7rem;">
          <h2 style="font-size: 1.125rem; font-weight: 700; color: #111827; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            Resumo do Pedido
          </h2>

          <div id="delivery-address" style="font-size:0.8125rem;color:#6b7280;margin-bottom:1rem;"></div>
          
          <div id="checkout-items" style="max-height: 200px; overflow-y: auto; margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem;">
            <!-- Itens injetados via JS -->
          </div>

          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="color: #6b7280; font-size: 0.875rem;">Subtotal</span>
            <span id="checkout-subtotal" style="font-weight: 500; color: #111827;">R$ 0,00</span>
          </div>
          <div style="margin-bottom: 1rem; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: #f9fafb;">
            <div style="font-size: 0.8rem; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 0.5rem;">
              Tipo de Frete
            </div>
            <div id="shipping-options" class="shipping-options"></div>
            <div id="shipping-note" style="font-size:0.75rem;color:#6b7280;margin-top:.5rem;">Calculando valores para o seu CEP...</div>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem;">
            <span style="color: #6b7280; font-size: 0.875rem;">Frete</span>
            <span id="checkout-frete" style="font-weight: 500; color: #16a34a;">R$ 0,00</span>
          </div>

          <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
            <span style="font-weight: 700; color: #111827; font-size: 1.125rem;">Total</span>
            <span id="checkout-total" style="font-weight: 900; color: #ca8a04; font-size: 1.5rem;">R$ 0,00</span>
          </div>

          <button id="confirm-payment-btn" onclick="finalizarCompra()" disabled style="width: 100%; background: #000; color: #fff; font-weight: 700; padding: 1rem; border-radius: 0.5rem; text-align: center; border: none; cursor: not-allowed; transition: background 0.2s; opacity: .6;" onmouseover="if(!this.disabled){this.style.background='#facc15'; this.style.color='#000';}" onmouseout="if(!this.disabled){this.style.background='#000'; this.style.color='#fff';}">
            Confirmar Pagamento
          </button>
        </div>
      </div>
      
    </div>
  </div>

  <!-- Modal Sucesso -->
  <div id="modal-sucesso" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:3rem 2rem; border-radius:1rem; text-align:center; max-width:400px; width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
      <div style="width:5rem;height:5rem;background:#dcfce3;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#16a34a"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      </div>
      <h2 style="font-size:1.5rem; font-weight:700; margin-bottom:0.75rem;">Pedido Confirmado!</h2>
      <p id="pedido-numero" style="font-size:1rem;color:#374151;font-weight:600;margin-bottom:.5rem;"></p>
      <p style="color:#6b7280; margin-bottom:2rem;">Obrigado por comprar na Spark Eletrônica. Seu pedido foi recebido e está sendo processado.</p>
      <a href="/index.php" style="display:inline-block; background:#000; color:#facc15; font-weight:700; padding:0.75rem 2rem; border-radius:0.5rem; text-decoration:none;">Voltar para a Loja</a>
    </div>
  </div>

</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
// Estado global de frete (acessível por finalizarCompra)
let shippingPrice = 0;
let selectedShippingCode = null;
let shippingOptionsMap = {};

document.addEventListener('DOMContentLoaded', async () => {
  const confirmBtn = document.getElementById('confirm-payment-btn');

  function syncConfirmButton() {
    if (!confirmBtn) return;
    const opt = selectedShippingCode ? shippingOptionsMap[selectedShippingCode] : null;
    const enabled = !!(selectedShippingCode && opt && !opt.disabled);
    confirmBtn.disabled = !enabled;
    confirmBtn.style.opacity = enabled ? '1' : '.6';
    confirmBtn.style.cursor = enabled ? 'pointer' : 'not-allowed';
  }

  // Aguarda sincronização do carrinho com o banco antes de renderizar
  const sb = getSB();
  if (sb && !window.APP_LOCAL_MODE) {
    const { data: { session } } = await sb.auth.getSession();
    if (!session) { window.location.href = '/login.php?redirect=/checkout.php'; return; }
    await syncCartFromDB(session.user.id);
  }

  const cart = (typeof Cart !== 'undefined') ? Cart.getAll() : [];
  const itemsContainer = document.getElementById('checkout-items');
  let subtotal = 0;
  const subtotalEl = document.getElementById('checkout-subtotal');
  const totalEl = document.getElementById('checkout-total');
  const freteEl = document.getElementById('checkout-frete');
  const shippingNoteEl = document.getElementById('shipping-note');
  const shippingOptionsEl = document.getElementById('shipping-options');

  if (cart.length === 0) {
    window.location.href = '/';
    return;
  }

  const shippingItems = cart.map(item => ({
    codprod: item.id,
    quantidade: parseInt(item.qty ?? item.quantity ?? 1, 10) || 1
  }));

  cart.forEach(item => {
    const qty = parseInt(item.qty ?? item.quantity ?? 1, 10) || 1;
    subtotal += item.price * qty;
    itemsContainer.innerHTML += `
      <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; font-size:0.875rem;">
        <div style="color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px;">
          ${qty}x ${item.name}
        </div>
        <div style="font-weight:500; color:#111827;">
          R$ ${(item.price * qty).toFixed(2).replace('.', ',')}
        </div>
      </div>
    `;
  });

  function formatBRL(value) {
    return 'R$ ' + Number(value || 0).toFixed(2).replace('.', ',');
  }

  function parseBrlValue(v) {
    if (v == null) return 0;
    return parseFloat(String(v).replace(/\./g, '').replace(',', '.')) || 0;
  }

  function updateTotals() {
    subtotalEl.innerText = formatBRL(subtotal);
    freteEl.innerText = formatBRL(shippingPrice);
    totalEl.innerText = formatBRL(subtotal + shippingPrice);
    syncConfirmButton();
  }

  function syncShippingUI() {
    shippingOptionsEl.querySelectorAll('.shipping-option').forEach(btn => {
      btn.classList.toggle('is-active', btn.dataset.shippingCode === selectedShippingCode);
    });
    const selected = shippingOptionsMap[selectedShippingCode];
    if (selected && selected.prazo) {
      shippingNoteEl.innerText = `${selected.label}: prazo estimado de ${selected.prazo} dia(s) útil(is).`;
    } else if (selected) {
      shippingNoteEl.innerText = `${selected.label}: calculado com sucesso.`;
    }
  }

  function renderShippingButtons() {
    shippingOptionsEl.innerHTML = '';
    Object.entries(shippingOptionsMap).forEach(([code, opt]) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'shipping-option';
      btn.dataset.shippingCode = code;
      const priceText = (opt && opt.valor != null && isFinite(opt.valor) && opt.valor > 0) ? ` - ${formatBRL(opt.valor)}` : '';
      btn.textContent = `${opt.label || code}${priceText}${opt.disabled ? ' (indisponível)' : ''}`;
      if (opt.disabled) btn.disabled = true;
      shippingOptionsEl.appendChild(btn);
    });
  }

  function applyShippingByCode(code) {
    const next = shippingOptionsMap[code];
    if (next && next.disabled) return;
    selectedShippingCode = code;
    const selected = shippingOptionsMap[code];
    shippingPrice = selected ? selected.valor : 0;
    syncShippingUI();
    updateTotals();
  }

  async function resolveCepDestino() {
    const enderecoId = <?= json_encode($endereco_id) ?>;
    if (!enderecoId) return null;
    const addrEl = document.getElementById('delivery-address');

    if (window.APP_LOCAL_MODE) {
      let addrs = [];
      try { addrs = JSON.parse(localStorage.getItem('spark_local_addresses') || '[]'); } catch(e) { addrs = []; }
      const addr = (addrs || []).find(a => String(a.id) === String(enderecoId));
      if (addr && addrEl) {
        const compl = addr.complemento ? ' - ' + String(addr.complemento) : '';
        addrEl.textContent = `Entrega: ${addr.logradouro}, ${addr.numero}${compl} — ${addr.bairro} — ${addr.cidade}/${addr.uf} — CEP ${addr.cep}`;
      }
      return addr?.cep ? String(addr.cep).replace(/\D/g, '') : null;
    }

    if (typeof getSB !== 'function') return null;
    const sb = getSB();
    if (!sb) return null;
    const { data, error } = await sb.from('endereco').select('cep,logradouro,numero,complemento,bairro,cidade,uf').eq('id', enderecoId).single();
    if (error || !data?.cep) return null;
    if (addrEl) {
      const compl = data.complemento ? ' - ' + String(data.complemento) : '';
      addrEl.textContent = `Entrega: ${data.logradouro}, ${data.numero}${compl} — ${data.bairro} — ${data.cidade}/${data.uf} — CEP ${data.cep}`;
    }
    return String(data.cep).replace(/\D/g, '');
  }

  async function loadShippingOptions() {
    const cepDestino = await resolveCepDestino();
    if (!cepDestino || cepDestino.length !== 8) {
      shippingNoteEl.innerText = 'CEP inválido. Não foi possível calcular o frete.';
      shippingOptionsMap = {};
      selectedShippingCode = null;
      shippingPrice = 0;
      renderShippingButtons();
      syncShippingUI();
      updateTotals();
      return;
    }

    try {
      const cepOrigem = String(window.APP_CEP_ORIGEM || '').replace(/\D/g, '');
      if (cepOrigem.length !== 8) throw new Error('cep_origem_invalido');

      const cartItemsFull = (typeof Cart !== 'undefined') ? Cart.getAll() : [];
      const pesoGramas = Math.max(1, Math.round(cartItemsFull.reduce((s, it) => {
        const pesoTotal = (it.pesoTotal != null) ? parseFloat(it.pesoTotal) : (parseFloat(it.peso || 0.3) * (parseInt(it.qty, 10) || 1));
        return s + (isFinite(pesoTotal) ? pesoTotal : 0) * 1000;
      }, 0))) || 300;
      const altura = Math.max(1, ...cartItemsFull.map(it => parseFloat((it.embAltura ?? it.altura) || 10)));
      const largura = Math.max(1, ...cartItemsFull.map(it => parseFloat((it.embLargura ?? it.largura) || 15)));
      const comprimento = Math.max(1, ...cartItemsFull.map(it => parseFloat((it.embComprimento ?? it.comprimento) || 20)));

      function parsePrecoItem(item) {
        if (!item || typeof item !== 'object') return null;
        const raw = item.pcTotal ?? item.pcFinal ?? item.pcServico ?? item.pcBase ?? null;
        if (raw == null) return null;
        const n = parseFloat(String(raw).replace(',', '.'));
        return isFinite(n) ? n : null;
      }

      async function fetchCorreiosPreco(code) {
        const res = await fetch('/api/correios-preco.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ coProduto: code, cepOrigem, cepDestino, pesoGramas, altura, largura, comprimento, valorDeclarado: subtotal })
        });
        const data = await res.json();
        if (data?.error) throw new Error(String(data.error));
        const valor = parsePrecoItem(data?.item);
        if (!valor || valor <= 0) throw new Error('preco_invalido');
        return { valor, nome: String(data?.item?.noProduto || data?.item?.coProduto || code) };
      }

      async function fetchCorreiosPrazo(code) {
        const res = await fetch('/api/correios-data-entrega.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ coProduto: code, cepOrigem, cepDestino })
        });
        const data = await res.json();
        if (data?.error) throw new Error(String(data.error));
        const prazo = data?.prazoEntrega ?? data?.prazoItem?.prazoEntrega ?? null;
        const n = prazo != null ? parseInt(prazo, 10) : null;
        return (n && isFinite(n) && n > 0) ? n : null;
      }

      const servicos = [
        { code: '03298', label: 'PAC' },
        { code: '03220', label: 'SEDEX' },
      ];

      const settled = await Promise.allSettled(servicos.map(async (s) => {
        const preco = await fetchCorreiosPreco(s.code);
        let prazo = null;
        try { prazo = await fetchCorreiosPrazo(s.code); } catch (e) { prazo = null; }
        return { code: s.code, label: s.label, valor: preco.valor, prazo };
      }));

      const nextMap = {};
      let okCount = 0;
      settled.forEach((s, idx) => {
        const svc = servicos[idx];
        if (s.status === 'fulfilled' && s.value && s.value.valor && s.value.valor > 0) {
          okCount += 1;
          nextMap[svc.code] = { valor: s.value.valor, prazo: s.value.prazo, label: svc.label, disabled: false };
        } else {
          nextMap[svc.code] = { valor: 0, prazo: null, label: svc.label, disabled: true };
        }
      });

      if (!okCount) throw new Error('sem_opcoes_frete');

      shippingOptionsMap = nextMap;
      renderShippingButtons();
      selectedShippingCode = null;
      shippingPrice = 0;
      syncShippingUI();
      updateTotals();
      if (okCount < servicos.length) {
        shippingNoteEl.innerText = 'Selecione uma opção de frete (algumas não puderam ser calculadas).';
      } else {
        shippingNoteEl.innerText = 'Selecione uma opção de frete.';
      }
    } catch (e) {
      shippingNoteEl.innerText = 'Erro ao calcular frete.';
      shippingOptionsMap = {};
      selectedShippingCode = null;
      shippingPrice = 0;
      renderShippingButtons();
      syncShippingUI();
      updateTotals();
    }
  }

  shippingOptionsEl?.addEventListener('click', (e) => {
    const btn = e.target.closest('.shipping-option');
    if (!btn) return;
    applyShippingByCode(btn.dataset.shippingCode);
  });

  updateTotals();
  renderShippingButtons();
  syncShippingUI();
  loadShippingOptions();
});

async function finalizarCompra() {
  const btn = document.getElementById('confirm-payment-btn') || document.querySelector('button[onclick="finalizarCompra()"]');
  const enderecoId = <?= json_encode($endereco_id) ?>;
  if (!selectedShippingCode || !shippingOptionsMap[selectedShippingCode] || shippingOptionsMap[selectedShippingCode].disabled) {
    alert('Selecione uma opção de frete para continuar.');
    return;
  }

  // Modo local: simula pedido sem banco
  if (window.APP_LOCAL_MODE) {
    btn.innerText = 'Processando...';
    btn.disabled = true;
    setTimeout(() => {
      if (typeof Cart !== 'undefined') Cart.clear();
      document.getElementById('pedido-numero').textContent = 'Pedido #' + Date.now().toString().slice(-6);
      document.getElementById('modal-sucesso').style.display = 'flex';
    }, 1200);
    return;
  }

  const sb = getSB();
  if (!sb) { alert('Erro de conexão.'); return; }

  const { data: { session } } = await sb.auth.getSession();
  if (!session) { window.location.href = '/login.php?redirect=/checkout.php'; return; }

  const cart = (typeof Cart !== 'undefined') ? Cart.getAll() : [];
  if (!cart.length) { window.location.href = '/'; return; }

  btn.innerText = 'Processando...';
  btn.disabled = true;
  btn.style.opacity = '0.7';

  try {
    const mpRes = await fetch('/checkout-pagamento.php?action=mp-preference', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + session.access_token,
      },
      body: JSON.stringify({
        endereco_id: String(enderecoId),
        shipping_code: selectedShippingCode,
      }),
    });
    const mpData = await mpRes.json().catch(() => null);
    if (!mpRes.ok || !mpData?.ok || !mpData?.init_point) {
      throw new Error(mpData?.error || 'Erro ao criar pedido.');
    }

    if (typeof Cart !== 'undefined') Cart.clear();
    window.location.href = mpData.init_point;
  } catch (e) {
    console.error('Erro ao finalizar pedido:', e);
    btn.innerText = 'Confirmar Pagamento';
    btn.disabled = false;
    btn.style.opacity = '1';
    alert(e?.message || 'Erro ao finalizar pedido. Tente novamente.');
  }
}
</script>
<style>
  @media (min-width: 1024px) {
    .checkout-grid { flex-direction: row !important; }
  }
  .shipping-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
  }
  .shipping-option {
    border: 1px solid #d1d5db;
    border-radius: .5rem;
    background: #fff;
    font-size: .8125rem;
    font-weight: 700;
    padding: .55rem .5rem;
    color: #374151;
    transition: .2s ease;
  }
  .shipping-option.is-active {
    border-color: #facc15;
    background: #fefce8;
    color: #92400e;
  }
</style>
</body>
</html>
