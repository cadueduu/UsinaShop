<?php
// header.php — Desktop navigation header (hidden on mobile)
if (!isset($categories)) {
    require_once __DIR__ . '/config.php';
    $categories = fetch_categories(false);
}
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$is_products = ($current_path === '/products.php' || $current_path === '/product.php');
$active_cat = isset($_GET['categoria']) ? (string)$_GET['categoria'] : '';
$is_account = ($current_path === '/login.php' || $current_path === '/conta.php');
$is_admin_path = (strpos($current_path, '/admin/') === 0);
?>
<header class="site-header" id="site-header">
  <div class="header-inner">

    <!-- Logo -->
    <div class="header-logo-zone">
      <a href="/index.php">
        <img src="/assets/images/produtos/logo.png" alt="Spark Eletrônica" class="header-logo"
             width="423" height="251" decoding="async" onerror="this.style.display='none'">
      </a>
    </div>

    <!-- Categories navigation -->
    <nav class="header-cats">
      <!-- All categories dropdown -->
      <div class="cat-dropdown">
        <button class="cat-dropdown-btn<?= $is_products ? ' active' : '' ?>" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="cat-dropdown-panel">
          Todas as Categorias
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="cat-dropdown-panel" id="cat-dropdown-panel">
          <a href="/products.php" class="cat-dropdown-all">Todos os Produtos</a>
          <?php foreach ($categories as $gid => $group): ?>
            <div class="cat-group-label">
              <a href="/products.php?categoria=<?= $gid ?>" class="cat-group-link"><?= htmlspecialchars(cat_name($group['descr_grupo'])) ?></a>
            </div>
            <?php foreach ($group['children'] as $sid => $sub): ?>
              <a href="/products.php?categoria=<?= $sid ?>" class="cat-group-item"><?= htmlspecialchars(cat_name($sub['descr_grupo'])) ?></a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </nav>

    <!-- Actions -->
    <div class="header-actions">
      <!-- Search -->
      <button id="search-open-btn" class="header-action-btn" type="button" aria-label="Buscar">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.15 4.15a7.5 7.5 0 0012.5 12.5z"/></svg>
      </button>

      <!-- Admin (shown via JS if is_admin) -->
      <a href="/admin/index.php" id="header-admin-btn" class="header-action-btn<?= $is_admin_path ? ' active' : '' ?>"<?= $is_admin_path ? ' aria-current="page"' : '' ?> style="display:none">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        <span class="header-action-sublabel">Acesso Restrito</span>
        <span class="header-action-label"></span>
      </a>

      <!-- Account -->
      <a href="/login.php" id="header-account-btn" class="header-action-btn<?= $is_account ? ' active' : '' ?>"<?= $is_account ? ' aria-current="page"' : '' ?>>
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        <span class="header-action-sublabel"></span>
        <span class="header-action-label">Entrar</span>
      </a>

      <!-- Cart -->
      <button id="cart-open-btn" class="header-action-btn" type="button" aria-label="Carrinho">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span class="cart-badge" id="cart-count-desktop" aria-live="polite" aria-atomic="true">0</span>
      </button>
    </div>

  </div>
</header>
