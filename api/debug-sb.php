<?php
require_once dirname(__DIR__) . '/includes/config.php';
header('Content-Type: text/html; charset=utf-8');

function raw_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'err' => $err, 'body' => $resp];
}

$base = SUPABASE_URL . '/rest/v1/';

echo '<pre style="font-family:monospace;font-size:13px;">';
echo "LOCAL_DATA_MODE: " . (LOCAL_DATA_MODE ? 'TRUE (usando JSON local)' : 'FALSE (usando Supabase)') . "\n";
echo "SUPABASE_URL: " . SUPABASE_URL . "\n\n";

// --- Test 1: Simple product fetch (no embed) ---
echo "=== 1. Produto simples (sem embed) ===\n";
$url = $base . 'produto?select=codprod,descrprod,comnome&limit=3';
echo "URL: $url\n";
$r = raw_get($url);
echo "HTTP: {$r['code']}\n";
if ($r['err']) echo "CURL ERROR: {$r['err']}\n";
$data = json_decode($r['body'], true);
echo "Resultado (" . count((array)$data) . " itens): " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// --- Test 2: Fetch preco separately ---
echo "=== 2. Preco separado ===\n";
$url2 = $base . 'preco?select=codprod,vlr_venda&limit=5';
echo "URL: $url2\n";
$r2 = raw_get($url2);
echo "HTTP: {$r2['code']}\n";
if ($r2['err']) echo "CURL ERROR: {$r2['err']}\n";
$data2 = json_decode($r2['body'], true);
echo "Resultado (" . count((array)$data2) . " itens): " . json_encode($data2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// --- Test 3: Fetch estoque separately ---
echo "=== 3. Estoque separado ===\n";
$url3 = $base . 'estoque?select=codprod,estoque_disponivel&limit=5';
echo "URL: $url3\n";
$r3 = raw_get($url3);
echo "HTTP: {$r3['code']}\n";
if ($r3['err']) echo "CURL ERROR: {$r3['err']}\n";
$data3 = json_decode($r3['body'], true);
echo "Resultado (" . count((array)$data3) . " itens): " . json_encode($data3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// --- Test 4: Embedded selects (old approach) ---
echo "=== 4. Produto + preco + estoque embed (abordagem antiga) ===\n";
$url4 = $base . 'produto?select=codprod,comnome,preco(vlr_venda),estoque(estoque_disponivel)&limit=3';
echo "URL: $url4\n";
$r4 = raw_get($url4);
echo "HTTP: {$r4['code']}\n";
if ($r4['err']) echo "CURL ERROR: {$r4['err']}\n";
$data4 = json_decode($r4['body'], true);
echo "Resultado: " . json_encode($data4, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// --- Test 5: Full enrich_products() via sb() ---
echo "=== 5. enrich_products() - nova abordagem ===\n";
$prods = sb('produto', ['select' => 'codprod,descrprod,comnome', 'limit' => '3']);
echo "Produtos brutos: " . count($prods) . " itens\n";
if (!empty($prods)) {
    $enriched = enrich_products($prods);
    $available = filter_available($enriched);
    echo "Após enrich_products: " . count($enriched) . " itens\n";
    echo "Após filter_available (price>0 && stock>0): " . count($available) . " itens\n";
    echo "Dados: " . json_encode($enriched, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// --- Test 6: Count products that would appear ---
echo "\n=== 6. Contagem total de produtos disponíveis ===\n";
$all = sb('produto', ['select' => 'codprod,descrprod,comnome', 'limit' => '500']);
echo "Total bruto: " . count($all) . " produtos\n";
if (!empty($all)) {
    $all_enriched = enrich_products($all);
    $all_available = filter_available($all_enriched);
    echo "Com preço > 0 e estoque > 0: " . count($all_available) . " produtos\n";
    if (!empty($all_available)) {
        echo "Primeiro disponível: " . json_encode($all_available[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo '</pre>';
