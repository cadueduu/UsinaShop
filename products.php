<?php
require_once __DIR__ . '/includes/config.php';

$cat_id = intval($_GET['categoria'] ?? 0);

// Categorias já são cacheadas em disco por fetch_categories(). É a única
// consulta síncrona ao Supabase que esta página faz antes do primeiro byte.
$categories = fetch_categories(false);

// Resolve grupo/subgrupo ativos para os filtros
$active_group = 0;
$active_sub   = 0;
$cat_name_str = 'Todos os Produtos';

if ($cat_id > 0) {
    if (isset($categories[$cat_id])) {
        $active_group = $cat_id;
        $cat_name_str = cat_name($categories[$cat_id]['descr_grupo']);
    } else {
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

$page_title = $cat_name_str;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';

// Flush o cabeçalho/filtros pro browser antes de qualquer query pesada.
// Os produtos vêm via /api/produtos.php (auto-disparado pelo initLoadMore).
@ob_flush(); @flush();
?>

<main class="page-content">
<div class="container" style="padding:2rem 1rem 4rem;">

  <!-- Page header -->
  <div class="page-header">
    <h1 class="page-title"><?= htmlspecialchars($cat_name_str) ?></h1>
    <p class="page-subtitle" id="products-subtitle">Carregando produtos…</p>
  </div>

  <!-- Category filter pills -->
  <div class="filters-container">
    <div class="filter-pills">
      <a href="/products.php" class="pill<?= $cat_id===0?' active':'' ?>">Todos</a>
      <?php foreach ($categories as $gid => $group): ?>
        <a href="/products.php?categoria=<?= $gid ?>"
           class="pill<?= $active_group===$gid&&$active_sub===0?' active':'' ?>">
          <?= htmlspecialchars(cat_name($group['descr_grupo'])) ?>
        </a>
      <?php endforeach; ?>
    </div>
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

  <!-- Products grid (preenchida via /api/produtos.php) -->
  <div class="products-grid" id="products-grid" aria-busy="true">
    <?php for ($i = 0; $i < 8; $i++): ?>
      <div class="product-card product-card--skeleton" aria-hidden="true">
        <div class="product-card__img" style="background:#f1f5f9;"></div>
        <div class="product-card__body">
          <div style="height:14px;background:#e2e8f0;border-radius:4px;margin:.4rem 0;width:80%;"></div>
          <div style="height:14px;background:#e2e8f0;border-radius:4px;margin:.4rem 0;width:50%;"></div>
          <div style="height:36px;background:#e2e8f0;border-radius:6px;margin-top:.6rem;"></div>
        </div>
      </div>
    <?php endfor; ?>
  </div>

  <div id="load-more-wrap" style="text-align:center;margin-top:2rem;">
    <button id="load-more-btn"
            class="btn-load-more"
            data-offset="0"
            data-categoria="<?= $cat_id ?>"
            data-q=""
            data-initial="1"
            style="visibility:hidden;">
      Ver mais produtos
    </button>
  </div>

</div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
