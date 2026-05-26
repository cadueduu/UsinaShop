<?php
require_once __DIR__ . '/includes/config.php';

$cat_id = intval($_GET['categoria'] ?? 0);

// Fetch all categories
$categories = fetch_categories(false);

// Build flat map of all categories
$all_cats = [];
foreach ($categories as $gid => $g) {
    $all_cats[$gid] = $g;
    foreach ($g['children'] as $sid => $s) {
        $all_cats[$sid] = $s;
    }
}

// Determine active group and sub-category
$active_group = 0;
$active_sub   = 0;
$cat_name_str = 'Todos os Produtos';

if ($cat_id > 0) {
    if (isset($categories[$cat_id])) {
        // Level 1 selected
        $active_group = $cat_id;
        $cat_name_str = cat_name($categories[$cat_id]['descr_grupo']);
    } else {
        // Level 2 selected — find parent
        foreach ($categories as $gid => $g) {
            if (isset($g['children'][$cat_id])) {
                $active_group = $gid;
                $active_sub   = $cat_id;
                $cat_name_str = cat_name($g['children'][$cat_id]['descr_grupo']);
                break;
            }
        }
    }
}

// Build list of codgrupoprod IDs to filter by
$filter_ids = [];
if ($active_sub > 0) {
    $filter_ids = [$active_sub];
} elseif ($active_group > 0) {
    // Include all sub-categories
    $filter_ids = array_keys($categories[$active_group]['children'] ?? []);
    if (empty($filter_ids)) $filter_ids = [$active_group];
}

// Fetch the first displayable page using the same batch-retry strategy as api/produtos.php.
// Each iteration fetches PROD_FETCH_BATCH raw rows; if a batch yields zero visible products
// (all filtered by stock/price/image) and the DB has more rows, we advance and retry —
// up to MAX_SSR_EMPTY_BATCHES times — so the SSR page never shows empty when valid
// products exist further along in the table (e.g. older rows without imagery).
$base_params = [
    'select' => 'codprod,descrprod,comnome,codgrupoprod',
    'limit'  => PROD_FETCH_BATCH,
    'order'  => 'codprod.asc',
];
if (!empty($filter_ids)) {
    $base_params['codgrupoprod'] = 'in.(' . implode(',', $filter_ids) . ')';
}

const MAX_SSR_EMPTY_BATCHES = 3;
$all_fetched    = [];
$has_more       = false;
$next_offset    = 0;
$empty_batches  = 0;

while (count($all_fetched) < PROD_PAGE_SIZE && $empty_batches <= MAX_SSR_EMPTY_BATCHES) {
    $params        = $base_params;
    $params['offset'] = $next_offset;
    $raw           = sb('produto', $params);
    $supabase_full = count($raw) === PROD_FETCH_BATCH;

    if (empty($raw)) { $has_more = false; break; }

    $batch = enrich_products($raw, true);

    if (empty($batch)) {
        if (!$supabase_full) { $has_more = false; break; }
        $empty_batches++;
        $next_offset += PROD_FETCH_BATCH;
        continue;
    }

    $empty_batches  = 0;
    $all_fetched    = array_merge($all_fetched, $batch);
    $has_more       = $supabase_full;
    $next_offset   += PROD_FETCH_BATCH;

    if (count($all_fetched) >= PROD_PAGE_SIZE) break;
    if (!$supabase_full) { $has_more = false; break; }
}

if (count($all_fetched) > PROD_PAGE_SIZE) $has_more = true;

$products = array_slice($all_fetched, 0, PROD_PAGE_SIZE);
$count    = count($all_fetched);

$page_title = $cat_name_str;

// Preload the first SSR product card image — it's the LCP candidate on the
// catalog page. Skip when the only image is the local fallback logo (already
// covered by the static asset cache, and we don't want to fight with the
// browser's heuristics over a logo).
if (!empty($products)) {
    $first_img = prod_img($products[0]['produto_imagem'] ?? []);
    if ($first_img !== '/assets/images/produtos/logo.png') {
        $preload_image = $first_img;
    }
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>

<main class="page-content">
<div class="container" style="padding:2rem 1rem 4rem;">

  <!-- Page header -->
  <div class="page-header">
    <h1 class="page-title"><?= htmlspecialchars($cat_name_str) ?></h1>
    <p class="page-subtitle">
      <?php if ($has_more): ?>
        Exibindo os primeiros <?= count($products) ?> produtos
      <?php elseif ($count === 0): ?>
        Nenhum produto encontrado
      <?php else: ?>
        <?= $count ?> produto<?= $count !== 1 ? 's' : '' ?> encontrado<?= $count !== 1 ? 's' : '' ?>
      <?php endif; ?>
    </p>
  </div>

  <!-- Category filter pills -->
  <div class="filters-container">
    <!-- Level 1 pills -->
    <div class="filter-pills">
      <a href="/products.php" class="pill<?= $cat_id===0?' active':'' ?>">Todos</a>
      <?php foreach ($categories as $gid => $group): ?>
        <a href="/products.php?categoria=<?= $gid ?>"
           class="pill<?= $active_group===$gid&&$active_sub===0?' active':'' ?>">
          <?= htmlspecialchars(cat_name($group['descr_grupo'])) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <!-- Level 2 pills (only if a group is selected) -->
    <?php if ($active_group > 0 && !empty($categories[$active_group]['children'])): ?>
    <div class="filter-sub-row">
      <a href="/products.php?categoria=<?= $active_group ?>"
         class="pill pill-sm<?= $active_sub===0?' active':'' ?>">Todos</a>
      <?php foreach ($categories[$active_group]['children'] as $sid => $sub): ?>
        <a href="/products.php?categoria=<?= $sid ?>"
           class="pill pill-sm<?= $active_sub===$sid?' active':'' ?>">
          <?= htmlspecialchars(cat_name($sub['descr_grupo'])) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Products grid -->
  <?php if (empty($products)): ?>
    <div class="no-products">
      <p>Nenhum produto encontrado nesta categoria.</p>
      <a href="/products.php">Ver todos os produtos →</a>
    </div>
  <?php else: ?>
    <div class="products-grid" id="products-grid">
      <?php foreach ($products as $p): ?>
        <?php include __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>

    <?php if ($has_more): ?>
    <div id="load-more-wrap" style="text-align:center;margin-top:2rem;">
      <button id="load-more-btn"
              class="btn-load-more"
              data-offset="<?= $next_offset ?>"
              data-categoria="<?= $cat_id ?>"
              data-q="">
        Ver mais produtos
      </button>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
