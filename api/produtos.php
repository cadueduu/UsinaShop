<?php
/**
 * Paginated product listing API — FAST PATH
 *
 * Antes: fetch raw produto (72 rows) → filter no PHP → enrich (preço/estoque/imagens),
 * com loop de retry quando o batch vinha todo indisponível. Custava 2-6 round-trips
 * de 400ms cada (1.5–6 s por chamada).
 *
 * Agora: 1 query embedded `preco!inner+estoque!inner` filtra disponibilidade NO BANCO
 * (1 round-trip, ~440ms), depois 1 query de imagens só para os IDs sobreviventes.
 * Total ~900ms cold; 0ms quente (cache em disco 60s).
 *
 * GET /api/produtos.php
 *   ?offset=N         Offset em produtos JÁ DISPONÍVEIS (não mais offset raw).
 *   &categoria=ID
 *   &q=term
 *   &limit=N          Opcional, máx PROD_PAGE_SIZE.
 */
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30, stale-while-revalidate=120');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$offset = max(0, intval($_GET['offset'] ?? 0));
$cat_id = intval($_GET['categoria'] ?? 0);
$q      = trim($_GET['q'] ?? '');
$limit  = max(1, min(PROD_PAGE_SIZE, intval($_GET['limit'] ?? PROD_PAGE_SIZE)));

// ─── Cache em camadas (APCu → disco) ─────────────────────────────────────────
require_once dirname(__DIR__) . '/includes/cache.php';
$cache_key = 'api_prods_v2_' . md5(json_encode([$offset, $cat_id, $q, $limit]));
$cache_ttl = 60;
if (!LOCAL_DATA_MODE) {
    $hit = cache_get($cache_key, $cache_ttl);
    if ($hit !== null && $hit !== '') {
        echo $hit;
        exit;
    }
}

// ─── Filtro de categoria (recursivo, igual a antes) ──────────────────────────
$cat_filter = '';
if ($cat_id > 0) {
    $categories = fetch_categories(false);
    $filter_ids = [];
    if (isset($categories[$cat_id])) {
        $filter_ids = array_keys($categories[$cat_id]['children'] ?? []);
        if (empty($filter_ids)) $filter_ids = [$cat_id];
    } else {
        foreach ($categories as $g) {
            if (isset($g['children'][$cat_id])) { $filter_ids = [$cat_id]; break; }
        }
    }
    if (!empty($filter_ids)) {
        $cat_filter = 'in.(' . implode(',', $filter_ids) . ')';
    }
}

// ─── LOCAL_DATA_MODE: caminho antigo (não tem embedded select) ───────────────
if (LOCAL_DATA_MODE) {
    $available = [];
    $params = [
        'select' => 'codprod,descrprod,comnome,codgrupoprod,peso,altura,largura,comprimento',
        'limit'  => PROD_FETCH_BATCH,
        'offset' => 0,
        'order'  => 'codprod.asc',
    ];
    if ($cat_filter !== '') $params['codgrupoprod'] = $cat_filter;
    if ($q !== '')          $params['descrprod']    = 'ilike.*' . $q . '*';
    $raw = sb('produto', $params);
    $available = enrich_products($raw, true);
    $default_img = '/assets/images/produtos/logo.png';
    $kept = [];
    $i = $offset;
    $n = count($available);
    while ($i < $n && count($kept) < $limit) {
        $p = $available[$i];
        $img = prod_img($p['produto_imagem'] ?? []);
        if ($img !== $default_img) $kept[] = $p;
        $i++;
    }
    $has_more = $i < $n;

    $cards = array_map(function ($p) {
        $price = (float)($p['preco'][0]['vlr_venda'] ?? 0);
        return [
            'id'        => (int)$p['codprod'],
            'name'      => prod_name($p),
            'price'     => $price,
            'price_fmt' => fmt_brl($price),
            'image'     => prod_img($p['produto_imagem'] ?? []),
            'peso'      => isset($p['peso']) ? (float)$p['peso'] : 0.3,
            'altura'    => isset($p['altura']) ? (int)$p['altura'] : 10,
            'largura'   => isset($p['largura']) ? (int)$p['largura'] : 15,
            'comprimento' => isset($p['comprimento']) ? (int)$p['comprimento'] : 20,
            'url'       => '/product.php?id=' . (int)$p['codprod'],
        ];
    }, $kept);

    echo json_encode([
        'ok'          => true,
        'products'    => array_values($cards),
        'has_more'    => $has_more,
        'next_offset' => $i,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Fast path: embedded inner joins + filtro no banco ───────────────────────
// Pega mais que o pedido para minimizar idas/voltas em scrolls subsequentes.
$fetch_size = max($limit, PROD_PAGE_SIZE);
$base_params = [
    'select' => 'codprod,descrprod,comnome,codgrupoprod,peso,altura,largura,comprimento,preco!inner(vlr_venda),estoque!inner(estoque_disponivel),produto_imagem(url,ordem)',
    'preco.vlr_venda'             => 'gt.0',
    'estoque.estoque_disponivel'  => 'gt.0',
    'limit'  => $fetch_size,
    'order'  => 'codprod.asc',
];
if ($cat_filter !== '') $base_params['codgrupoprod'] = $cat_filter;
if ($q !== '')          $base_params['descrprod']    = 'ilike.*' . $q . '*';

$default_img = '/assets/images/produtos/logo.png';
$kept = [];
$raw_consumed = 0;
$last_batch_count = 0;
$max_loops = 10;

for ($loop = 0; $loop < $max_loops && count($kept) < $limit; $loop++) {
    $params = $base_params;
    $params['offset'] = $offset + $raw_consumed;
    $rows = sb('produto', $params);
    if (!is_array($rows) || empty($rows)) {
        $last_batch_count = 0;
        break;
    }

    $last_batch_count = count($rows);
    $raw_consumed += $last_batch_count;

    $ids = [];
    foreach ($rows as &$r) {
        $ids[] = (int)$r['codprod'];
        if (isset($r['estoque']) && is_array($r['estoque']) && !isset($r['estoque'][0])) {
            $r['estoque'] = [$r['estoque']];
        }
        if (isset($r['preco']) && is_array($r['preco']) && !isset($r['preco'][0])) {
            $r['preco'] = [$r['preco']];
        }
        if (!isset($r['produto_imagem']) || !is_array($r['produto_imagem'])) {
            $r['produto_imagem'] = [];
        }
    }
    unset($r);

    if (!empty($ids)) {
        $bearer = (SUPABASE_SERVICE_KEY !== '') ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
        $ext_rows = sb('ext_product_images', [
            'select'          => 'product_code,file_path,position,updated_at,created_at',
            'product_code'    => 'in.(' . implode(',', $ids) . ')',
            'resolution_type' => 'eq.low',
            'deleted_at'      => 'is.null',
            'order'           => 'product_code.asc,position.asc',
        ], 'GET', null, $bearer);

        $ext_map = [];
        foreach ((array)$ext_rows as $row) {
            if (!is_array($row)) continue;
            $code = (string)($row['product_code'] ?? '');
            if ($code === '') continue;
            $path = trim((string)($row['file_path'] ?? ''));
            if ($path === '') continue;
            if (preg_match('/(^|[\/_.\-])high([\/_.\-]|$)/i', $path)) continue;
            $ext_map[$code][] = [
                'path'    => $path,
                'version' => (string)($row['updated_at'] ?? ($row['created_at'] ?? '')),
                'ordem'   => intval($row['position'] ?? 0),
            ];
        }
        foreach ($rows as &$r) {
            $pid = (string)intval($r['codprod']);
            if (!empty($ext_map[$pid])) {
                $r['produto_imagem'] = $ext_map[$pid];
            }
        }
        unset($r);
    }

    foreach ($rows as $p) {
        if (count($kept) >= $limit) break;
        $img = prod_img($p['produto_imagem'] ?? []);
        if ($img === $default_img) continue;
        $kept[] = $p;
    }

    if ($last_batch_count < $fetch_size) break;
}

$has_more = $last_batch_count === $fetch_size;

$cards = array_map(function ($p) {
    $price = (float)($p['preco'][0]['vlr_venda'] ?? 0);
    return [
        'id'        => (int)$p['codprod'],
        'name'      => prod_name($p),
        'price'     => $price,
        'price_fmt' => fmt_brl($price),
        'image'     => prod_img($p['produto_imagem'] ?? []),
        'peso'      => isset($p['peso']) ? (float)$p['peso'] : 0.3,
        'altura'    => isset($p['altura']) ? (int)$p['altura'] : 10,
        'largura'   => isset($p['largura']) ? (int)$p['largura'] : 15,
        'comprimento' => isset($p['comprimento']) ? (int)$p['comprimento'] : 20,
        'url'       => '/product.php?id=' . (int)$p['codprod'],
    ];
}, $kept);

$payload = json_encode([
    'ok'          => true,
    'products'    => array_values($cards),
    'has_more'    => $has_more,
    'next_offset' => $offset + $raw_consumed,
], JSON_UNESCAPED_UNICODE);

if (!LOCAL_DATA_MODE) cache_set($cache_key, $payload, $cache_ttl);
echo $payload;
