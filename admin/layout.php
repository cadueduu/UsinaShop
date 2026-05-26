<?php
// admin/layout.php — Admin shell layout
// Expects $admin_page_title and $admin_active_nav to be set
$admin_page_title = $admin_page_title ?? 'Admin';
$admin_active     = $admin_active     ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($admin_page_title) ?> | Admin Spark</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <script>window.APP_LOCAL_MODE = <?= defined('LOCAL_DATA_MODE') && LOCAL_DATA_MODE ? 'true' : 'false' ?>;</script>
</head>
<body>
<div class="admin-layout">

  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <div class="admin-logo">
      <img src="/assets/images/produtos/logo.png" alt="Spark Admin" onerror="this.style.display='none'">
    </div>
    <nav class="admin-nav">
      <a href="/admin/clientes.php" class="admin-nav-item <?= $admin_active==='clientes'?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Clientes
      </a>
      <a href="/admin/produtos.php" class="admin-nav-item <?= $admin_active==='produtos'?'active':'' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        Produtos
      </a>
    </nav>
    <div style="padding:1rem 1.25rem;border-top:1px solid #1f2937;">
      <a href="/index.php" style="color:#9ca3af;font-size:.8125rem;display:flex;align-items:center;gap:.5rem;transition:color .2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9ca3af'">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        Ver Loja
      </a>
    </div>
  </aside>

  <!-- Content area -->
  <div class="admin-content">
    <!-- Top bar -->
    <div class="admin-topbar">
      <span class="admin-page-title"><?= htmlspecialchars($admin_page_title) ?></span>
      <div id="admin-user-label" style="font-size:.875rem;color:#6b7280;"></div>
    </div>

    <!-- Page body injected here -->
    <div class="admin-body" id="admin-body">

