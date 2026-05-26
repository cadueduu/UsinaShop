<?php
// mobile-bar.php — Mobile bottom navigation bar
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$is_home = ($current_path === '/' || $current_path === '/index.php');
$is_account = ($current_path === '/conta.php' || $current_path === '/login.php');
$is_admin_path = (strpos($current_path, '/admin/') === 0);
?>
<nav class="mobile-bar">
  <?php if (!$is_home): ?>
  <a href="/index.php" class="mobile-bar__btn">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
    <span>Home</span>
  </a>
  <?php endif; ?>

  <button id="mobile-search-btn" class="mobile-bar__btn" type="button">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.15 4.15a7.5 7.5 0 0012.5 12.5z"/></svg>
    <span>Buscar</span>
  </button>

  <button id="mobile-cart-btn" class="mobile-bar__btn" type="button" style="position:relative;">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    <span class="mobile-cart-badge" id="mobile-cart-count" aria-live="polite" aria-atomic="true">0</span>
    <span>Carrinho</span>
  </button>

  <a href="/conta.php" id="mobile-account-btn" class="mobile-bar__btn<?= $is_account ? ' active' : '' ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    <span>Conta</span>
  </a>

  <a href="/admin/index.php" id="mobile-admin-btn" class="mobile-bar__btn<?= $is_admin_path ? ' active' : '' ?>" style="display:none;">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    <span>Admin</span>
  </a>
</nav>
