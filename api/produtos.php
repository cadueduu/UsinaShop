<?php
/**
 * Paginated product listing API
 *
 * GET /api/produtos.php
 *   ?offset=0        Raw Supabase offset — advances by PROD_FETCH_BATCH each page
 *   &categoria=ID    Optional category filter
 *   &q=term          Optional ilike search on descrprod
 *
 * Returns JSON:
 *   { ok, products: [{id, name, price, price_fmt, image, url}], has_more, next_offset }
 *
 * Pagination contract:
 *   - Each call fetches PROD_FETCH_BATCH (72) raw rows from Supabase, starting at $offset.
 *   - Rows are enriched (listing_mode: no specs, filter before images) then filtered.
 *   - Up to PROD_PAGE_SIZE (24) available products are returned.
 *   - If a batch returns 0 available products but the DB has more rows, the API advances
 *     the offset internally (up to MAX_EMPTY_BATCHES times) so the caller never gets
 *     an empty success response when products do exist further along.
 */
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
// Public product data — safe to cache at CDN/proxy level for 30 s; serve stale for 2 min.
header('Cache-Control: public, max-age=30, stale-while-revalidate=120');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

const MAX_EMPTY_BATCHES = 3; // advance through at most this many all-unavailable batches

$offset = max(0, intval($_GET['offset'] ?? 0));
$cat_id = intval($_GET['categoria'] ?? 0);
$q      = trim($_GET['q'] ?? '');

// ─── Category filter ──────────────────────────────────────────────────────────
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

// ─── Fetch + enrich loop (advances through all-unavailable batches) ───────────
$available      = [];
$has_more       = false;
$empty_batches  = 0;
$current_offset = $offset;

while (count($available) < PROD_PAGE_SIZE && $empty_batches <= MAX_EMPTY_BATCHES) {
    $params = [
        'select' => 'codprod,descrprod,comnome,codgrupoprod',
        'limit'  => PROD_FETCH_BATCH,
        'offset' => $current_offset,
        'order'  => 'codprod.asc',
    ];
    if ($cat_filter !== '') $params['codgrupoprod'] = $cat_filter;
    if ($q !== '')          $params['descrprod']    = 'ilike.*' . $q . '*';

    $raw = sb('produto', $params);

    if (empty($raw)) {
        $has_more = false;
        break;
    }

    // listing_mode=true: skips specs query, filters before image fetch, returns filtered list
    $batch_available = enrich_products($raw, true);

    if (count($batch_available) === 0) {
        // This DB batch had no available products; try the next one if more rows exist
        if (count($raw) < PROD_FETCH_BATCH) {
            $has_more = false;
            break;
        }
        $empty_batches++;
        $current_offset += PROD_FETCH_BATCH;
        continue;
    }

    $empty_batches   = 0;
    $available       = array_merge($available, $batch_available);
    $has_more        = count($raw) === PROD_FETCH_BATCH;
    $current_offset += PROD_FETCH_BATCH;

    // Stop as soon as we have a full page (avoid over-fetching)
    if (count($available) >= PROD_PAGE_SIZE) break;

    // Supabase returned fewer rows than requested — end of data
    if (count($raw) < PROD_FETCH_BATCH) { $has_more = false; break; }
}

$page = array_slice($available, 0, PROD_PAGE_SIZE);

// If we collected more than a page worth, there's definitely more to show
if (count($available) > PROD_PAGE_SIZE) $has_more = true;

// ─── Shape for JSON ───────────────────────────────────────────────────────────
$cards = array_map(function ($p) {
    $price = (float)($p['preco'][0]['vlr_venda'] ?? 0);
    return [
        'id'        => (int)$p['codprod'],
        'name'      => prod_name($p),
        'price'     => $price,
        'price_fmt' => fmt_brl($price),
        'image'     => prod_img($p['produto_imagem'] ?? []),
        'url'       => '/product.php?id=' . (int)$p['codprod'],
    ];
}, $page);

echo json_encode([
    'ok'          => true,
    'products'    => array_values($cards),
    'has_more'    => $has_more,
    'next_offset' => $current_offset,
], JSON_UNESCAPED_UNICODE);
