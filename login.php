<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Entrar / Cadastrar';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> | Spark Eletrônica</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <script>
    window.APP_LOCAL_MODE  = <?= defined('LOCAL_DATA_MODE') && LOCAL_DATA_MODE ? 'true' : 'false' ?>;
    window.APP_SB_URL      = <?= json_encode(SUPABASE_URL) ?>;
    window.APP_SB_ANON     = <?= json_encode(SUPABASE_ANON_KEY) ?>;
  </script>
</head>
<body>

<!-- Sidebars (global) -->
<?php include __DIR__ . '/includes/cart-sidebar.php'; ?>
<?php include __DIR__ . '/includes/search-sidebar.php'; ?>

<div class="auth-page">
  <div class="auth-card">

    <!-- Logo (mobile) -->
    <img src="/assets/images/produtos/logo.png" alt="Spark Eletrônica" class="auth-logo"
         width="423" height="251" decoding="async" onerror="this.style.display='none'">
    <h1 style="text-align:center;font-size:1.5rem;font-weight:700;margin-bottom:1.5rem;color:#111827;">Spark Eletrônica</h1>

    <!-- Tabs -->
    <div class="auth-tabs">
      <button id="tab-login" class="auth-tab active" type="button">Entrar</button>
      <button id="tab-signup" class="auth-tab" type="button">Cadastrar</button>
      <div id="tab-indicator" class="auth-tab-indicator" style="left:0%"></div>
    </div>

    <!-- Login Form -->
    <form id="login-form" class="auth-form active" novalidate>
      <div class="auth-error" style="display:none;"></div>
      <div class="input-group">
        <span class="icon-left">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </span>
        <input type="email" name="email" class="input-field" placeholder="Seu e-mail" autocomplete="email" required>
      </div>
      <div class="input-group">
        <span class="icon-left">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </span>
        <input type="password" name="password" class="input-field" placeholder="Senha" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="height:3.25rem;border-radius:.5rem;">Entrar</button>
    </form>

    <!-- Signup Form -->
    <form id="signup-form" class="auth-form" novalidate>
      <div class="auth-error" style="display:none;"></div>
      <div class="auth-form-grid">
        <div class="input-group">
          <span class="icon-left">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </span>
          <input type="text" name="nome" class="input-field" placeholder="Nome" required>
        </div>
        <div class="input-group">
          <span class="icon-left">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </span>
          <input type="text" name="sobrenome" class="input-field" placeholder="Sobrenome">
        </div>
      </div>
      <div class="input-group">
        <span class="icon-left">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </span>
        <input type="email" name="email" class="input-field" placeholder="Seu e-mail" required>
      </div>
      <div class="input-group">
        <span class="icon-left">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        </span>
        <input type="tel" name="telefone" class="input-field" placeholder="(00) 00000-0000">
      </div>
      <div class="input-group">
        <span class="icon-left">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
        </span>
        <input type="text" name="cpf" class="input-field" placeholder="CPF: 000.000.000-00" maxlength="14">
      </div>
      <div class="input-group">
        <span class="icon-left">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </span>
        <input type="password" name="password" class="input-field" placeholder="Senha (mín. 6 caracteres)" minlength="6" required>
      </div>
      <div class="input-group">
        <span class="icon-left">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </span>
        <input type="password" name="password2" class="input-field" placeholder="Confirmar senha" required>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="height:3.25rem;border-radius:.5rem;">Cadastrar</button>
    </form>

    <!-- Signup success screen -->
    <div id="signup-success" class="auth-success-screen" style="display:none;">
      <div class="auth-success-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#ca8a04"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      </div>
      <h2 style="font-size:1.25rem;font-weight:700;">Verifique seu e-mail</h2>
      <p style="text-align:center;color:#6b7280;font-size:.875rem;">Enviamos um link de confirmação. Acesse seu e-mail para ativar sua conta.</p>
      <button id="go-to-login" class="btn btn-primary" onclick="document.getElementById('tab-login').click();document.getElementById('signup-success').style.display='none';document.getElementById('signup-form').style.display='';">Ir para o Login</button>
    </div>

    <a href="/index.php" class="auth-back-link">← Voltar para a loja</a>
  </div>
</div>

<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>

