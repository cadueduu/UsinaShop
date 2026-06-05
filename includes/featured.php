<?php
// Curated codprod lists — manually picked, real products with price/stock/images.
// Swap these out to change what shows on the homepage.
const SPARK_HIGHLIGHTS_CODPROD = [
    311603,    // Carregador Charger 12V 32A
    313405,    // Inversor 1500W 12V 120V
    313407,    // Inversor 1800W 24V 120V
    311618,    // Conversor DC-DC 60A 24V/12V
    3125017,   // Fonte Connect 48V 30A Bivolt
    3125033,   // Fonte Connect 12V 200A Monovolt
    311801,    // Central Fonte Smart Connect
    313401,    // Inversor 1000W 12V 120V
];

// ─── Featured products (admin-managed) ───────────────────────────────────────
function featured_file(): string
{
    return dirname(__DIR__) . '/data/featured.json';
}

function featured_get_codprods(): array
{
    $file = featured_file();
    if (!is_file($file)) return [];
    $json = @file_get_contents($file);
    $data = json_decode((string)$json, true);
    if (!is_array($data) || !isset($data['codprods']) || !is_array($data['codprods'])) return [];
    return array_values(array_unique(array_map('intval', $data['codprods'])));
}

function featured_set_codprods(array $codprods): void
{
    $file = featured_file();
    $dir  = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $clean = array_values(array_unique(array_map('intval', $codprods)));
    @file_put_contents($file, json_encode(['codprods' => $clean], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function featured_toggle(int $codprod): bool
{
    $list = featured_get_codprods();
    $idx  = array_search($codprod, $list, true);
    if ($idx === false) {
        $list[] = $codprod;
        $now_on = true;
    } else {
        array_splice($list, $idx, 1);
        $now_on = false;
    }
    featured_set_codprods($list);
    // Invalidate cache so the homepage reflects the change immediately.
    @unlink(sys_get_temp_dir() . '/spark_highlights_8.json');
    return $now_on;
}

const SPARK_BESTSELLERS_CODPROD = [
    313409,    // Inversor 600W 12V 120V
    3134011,   // Inversor 800W 24V 120V
    313403,    // Inversor 1200W 24V 120V
    31340553,  // Inversor 3000W 12V 220V
    31340551,  // Inversor 5000W 24V 220V
    31160509,  // Carregador Charger 12V 60A
    311616,    // Conversor DC-DC 30A 12V/24V
    3125045,   // Fonte Bat Meter 12V 200A Monovolt
];

const SPARK_PRODUCT_SELECT = 'codprod,descrprod,comnome,desccurta,codgrupoprod,peso,altura,largura,comprimento';

// Fetch a curated list of products by codprod, preserving the given order.
function spark_resolve_codprod_list(array $codprods, int $limit): array
{
    $codprods = array_values(array_unique(array_map('intval', $codprods)));
    if (empty($codprods)) return [];

    $rows = sb('produto', [
        'select'  => SPARK_PRODUCT_SELECT,
        'codprod' => 'in.(' . implode(',', $codprods) . ')',
    ]);

    $by_id = [];
    foreach ((array)$rows as $p) {
        $by_id[(int)($p['codprod'] ?? 0)] = $p;
    }
    $ordered = [];
    foreach ($codprods as $id) {
        if (isset($by_id[$id])) $ordered[] = $by_id[$id];
    }

    $enriched = enrich_products($ordered, true);
    if (count($ordered) > 0 && empty($enriched)) {
        error_log('[featured] curated codprod list dropped by enrich (price/stock/image gate): ' . implode(',', $codprods));
    }
    return array_slice($enriched, 0, $limit);
}

// Pad a list out to $limit with generic catalog products so the section is
// never empty. Skips ids already present.
function spark_top_up(array $products, int $limit, array $extra_params = []): array
{
    if (count($products) >= $limit) return array_slice($products, 0, $limit);
    $have = [];
    foreach ($products as $p) $have[(int)($p['codprod'] ?? 0)] = true;

    $need  = $limit - count($products);
    $extra = fetch_products($extra_params, max($need * 3, 24));
    foreach ($extra as $p) {
        $cid = (int)($p['codprod'] ?? 0);
        if ($cid === 0 || isset($have[$cid])) continue;
        $products[] = $p;
        $have[$cid] = true;
        if (count($products) >= $limit) break;
    }
    return $products;
}

function spark_featured_cache(string $key, int $ttl, callable $producer): array
{
    if (LOCAL_DATA_MODE) return $producer();
    $file = sys_get_temp_dir() . '/spark_' . $key . '.json';
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        $cached = json_decode((string)file_get_contents($file), true);
        if (is_array($cached) && !empty($cached)) return $cached;
    }
    $value = $producer();
    if (!empty($value)) {
        @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return $value;
}

function fetch_highlights(int $limit = 8): array
{
    if (LOCAL_DATA_MODE) return fetch_products([], $limit);
    return spark_featured_cache('highlights_' . $limit, 300, function () use ($limit) {
        $admin_list = featured_get_codprods();
        $source     = !empty($admin_list) ? $admin_list : SPARK_HIGHLIGHTS_CODPROD;
        $picked     = spark_resolve_codprod_list($source, $limit);
        $out        = spark_top_up($picked, $limit);
        if (empty($out)) error_log('[featured] highlights empty after top-up');
        return $out;
    });
}

// Best sellers: aggregate quantity sold across fulfilled orders. Embeds
// pedido!inner so PostgREST joins and the pedido.status filter restricts to
// paid/shipped/delivered. Falls back to the seed list when there are no sales.
function fetch_best_sellers(int $limit = 8): array
{
    if (LOCAL_DATA_MODE) return fetch_products(['order' => 'codprod.desc'], $limit);

    return spark_featured_cache('bestsellers_' . $limit, 300, function () use ($limit) {
        $rows = sb('pedido_item', [
            'select'        => 'codprod,quantidade,pedido!inner(status)',
            'pedido.status' => 'in.(pago,enviado,entregue)',
            'limit'         => 5000,
        ]);
        error_log('[featured] best-sellers pedido_item rows=' . count((array)$rows));

        $totals = [];
        foreach ((array)$rows as $r) {
            $cp = (int)($r['codprod'] ?? 0);
            if ($cp === 0) continue;
            $totals[$cp] = ($totals[$cp] ?? 0) + (int)($r['quantidade'] ?? 0);
        }

        $enriched = [];
        if (!empty($totals)) {
            arsort($totals);
            $top_ids = array_slice(array_keys($totals), 0, $limit * 3);

            $products = sb('produto', [
                'select'  => SPARK_PRODUCT_SELECT,
                'codprod' => 'in.(' . implode(',', $top_ids) . ')',
            ]);

            $by_id = [];
            foreach ((array)$products as $p) {
                $by_id[(int)$p['codprod']] = $p;
            }
            $ordered = [];
            foreach ($top_ids as $id) {
                if (isset($by_id[$id])) $ordered[] = $by_id[$id];
            }
            $enriched = enrich_products($ordered, true);
        }

        // If sales ranking yielded too few survivors, supplement with the
        // curated best-sellers list, then top up with generic catalog products.
        if (count($enriched) < $limit) {
            $seed = spark_resolve_codprod_list(SPARK_BESTSELLERS_CODPROD, $limit);
            $have = [];
            foreach ($enriched as $p) $have[(int)$p['codprod']] = true;
            foreach ($seed as $p) {
                $cid = (int)$p['codprod'];
                if (!isset($have[$cid])) {
                    $enriched[] = $p;
                    $have[$cid] = true;
                }
            }
        }

        $out = spark_top_up($enriched, $limit, ['order' => 'codprod.desc']);
        if (empty($out)) error_log('[featured] best-sellers empty after top-up');
        return $out;
    });
}
