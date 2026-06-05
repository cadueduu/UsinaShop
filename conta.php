<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/csrf.php';
csrf_session_start();

$action = (string)($_GET['action'] ?? '');
if ($action === 'mp-confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!csrf_validate_request()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token de sessão inválido. Recarregue a página e tente novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    if (!defined('MP_ACCESS_TOKEN') || MP_ACCESS_TOKEN === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Mercado Pago não configurado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Servidor sem chave de serviço do Supabase.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paymentId = trim((string)($body['payment_id'] ?? ''));
    $externalRef = trim((string)($body['external_reference'] ?? ''));
    if ($paymentId === '' || $externalRef === '' || !ctype_digit($externalRef)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Dados inválidos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ch = curl_init('https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Erro ao validar pagamento.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($code < 200 || $code >= 300) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Erro ao validar pagamento.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode((string)$resp, true);
    $status = is_array($data) ? (string)($data['status'] ?? '') : '';
    $ref = is_array($data) ? (string)($data['external_reference'] ?? '') : '';

    if ($status !== 'approved' || $ref !== $externalRef) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Pagamento não aprovado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pedidoId = intval($externalRef);
    sb('pedido', ['id' => 'eq.' . $pedidoId], 'PATCH', ['status' => 'pago'], SUPABASE_SERVICE_KEY);
    echo json_encode(['ok' => true, 'pedido_id' => $pedidoId], JSON_UNESCAPED_UNICODE);
    exit;
}

$page_title = 'Minha Conta';
$categories = fetch_categories(false);
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>

<main class="page-content">
<div class="container" style="max-width:64rem;padding:3rem 1rem 5rem;">

  <!-- Page title -->
  <h1 class="page-title" style="font-size:1.875rem;margin-bottom:2rem;">Minha Conta</h1>

  <div id="payment-success-msg" class="alert alert-success" style="display:none;margin-bottom:1rem;">
    Pedido finalizado com sucesso.
  </div>

  <!-- Not logged in message (shown by JS) -->
  <div id="not-logged-msg" class="alert alert-info" style="display:none;">
    Você precisa estar <a href="/login.php" style="font-weight:700;text-decoration:underline;">logado</a> para acessar esta página.
  </div>

  <!-- Account layout (shown by JS when logged in) -->
  <div id="account-layout" class="account-layout" style="display:none;">

    <!-- Sidebar nav -->
    <aside class="account-sidebar">
      <div class="account-nav-item active" onclick="switchTab('profile')" id="nav-profile">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Meu Perfil
      </div>
      <div class="account-nav-item" onclick="switchTab('addresses')" id="nav-addresses">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Endereços
      </div>
      <div class="account-nav-item" onclick="switchTab('orders')" id="nav-orders">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Meus Pedidos
      </div>
      <div id="nav-admin" class="account-nav-item admin-link" style="display:none;border-top:1px solid #e5e7eb;" onclick="window.location='/admin/index.php'">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        Painel Admin
      </div>
      <div class="account-nav-item danger" style="border-top:1px solid #e5e7eb;" onclick="UserStore.logout()">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Sair
      </div>
    </aside>

    <!-- Main content -->
    <div class="account-main">

      <!-- Profile tab -->
      <div id="tab-profile" class="account-section active">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
          <h2 style="font-size:1.125rem;font-weight:700;">Meu Perfil</h2>
          <button id="edit-profile-btn" class="btn btn-outline btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Editar dados
          </button>
        </div>

        <!-- View mode -->
        <div id="profile-view">
          <div class="profile-grid">
            <div class="profile-field">
              <span class="profile-field-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
              <div><div class="profile-field-label">Nome completo</div><div class="profile-field-val" id="pf-name">—</div></div>
            </div>
            <div class="profile-field">
              <span class="profile-field-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span>
              <div><div class="profile-field-label">E-mail</div><div class="profile-field-val" id="pf-email">—</div></div>
            </div>
            <div class="profile-field">
              <span class="profile-field-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span>
              <div><div class="profile-field-label">Telefone</div><div class="profile-field-val" id="pf-phone">—</div></div>
            </div>
            <div class="profile-field">
              <span class="profile-field-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg></span>
              <div><div class="profile-field-label">CPF/CNPJ</div><div class="profile-field-val" id="pf-cpf">—</div></div>
            </div>
          </div>
        </div>

        <!-- Edit mode (hidden by default) -->
        <div id="profile-edit" style="display:none;">
          <div class="alert alert-error" id="profile-error" style="display:none;"></div>
          <div class="alert alert-success" id="profile-success" style="display:none;">Dados atualizados com sucesso!</div>
          <div class="form-grid" style="margin-bottom:1rem;">
            <div class="form-field col-span-2">
              <label class="form-label">Nome completo</label>
              <input type="text" id="edit-name" class="input-field" placeholder="Nome completo">
            </div>
            <div class="form-field col-span-2">
              <label class="form-label">E-mail</label>
              <input type="email" id="edit-email" class="input-field input-readonly" readonly>
            </div>
            <div class="form-field">
              <label class="form-label">Telefone</label>
              <input type="tel" id="edit-phone" class="input-field" placeholder="(00) 00000-0000">
            </div>
            <div class="form-field">
              <label class="form-label">CPF/CNPJ</label>
              <input type="text" id="edit-cpf" class="input-field input-readonly" readonly>
            </div>
          </div>
          <div style="display:flex;gap:.75rem;">
            <button id="save-profile-btn" class="btn btn-primary">Salvar alterações</button>
            <button id="cancel-profile-btn" class="btn btn-outline">Cancelar</button>
          </div>
        </div>
      </div><!-- /profile tab -->

      <!-- Orders tab -->
      <div id="tab-orders" class="account-section">
        <h2 style="font-size:1.125rem;font-weight:700;margin-bottom:1.25rem;">Meus Pedidos</h2>
        <div id="acct-orders-list">
          <div class="skeleton" style="height:5rem;border-radius:.5rem;margin-bottom:.75rem;"></div>
          <div class="skeleton" style="height:5rem;border-radius:.5rem;margin-bottom:.75rem;"></div>
        </div>
      </div>

      <!-- Addresses tab -->
      <div id="tab-addresses" class="account-section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
          <h2 style="font-size:1.125rem;font-weight:700;">Meus Endereços</h2>
          <button id="new-addr-toggle-btn" class="btn btn-yellow btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m-8-8h16"/></svg>
            Novo Endereço
          </button>
        </div>

        <!-- New address form -->
        <div id="acct-new-addr-form" class="checkout-form" style="display:none;margin-bottom:1rem;" data-address-form>
          <h3 class="checkout-form-title">Cadastrar Endereço</h3>
          <div class="alert alert-error" id="acct-addr-error" style="display:none;"></div>
          <div class="form-grid">
            <div class="form-field"><label class="form-label">CEP *</label><input type="text" name="cep" class="input-field" placeholder="00000-000" maxlength="9" data-cep-input inputmode="numeric"></div>
            <div class="form-field col-span-2"><label class="form-label">Logradouro *</label><input type="text" name="logradouro" class="input-field" data-field="logradouro"></div>
            <div class="form-field"><label class="form-label">Número *</label><input type="text" name="numero" class="input-field"></div>
            <div class="form-field"><label class="form-label">Complemento</label><input type="text" name="complemento" class="input-field"></div>
            <div class="form-field"><label class="form-label">Bairro</label><input type="text" name="bairro" class="input-field" data-field="bairro"></div>
            <div class="form-field"><label class="form-label">Cidade</label><input type="text" name="cidade" class="input-field" data-field="cidade"></div>
            <div class="form-field"><label class="form-label">UF</label><input type="text" name="uf" class="input-field" maxlength="2" style="text-transform:uppercase;" data-field="uf"></div>
          </div>
          <div style="display:flex;gap:.75rem;margin-top:1rem;">
            <button type="button" id="acct-save-addr" class="btn btn-primary">Salvar</button>
            <button type="button" id="acct-cancel-addr" class="btn btn-outline">Cancelar</button>
          </div>
        </div>

        <!-- Addresses list -->
        <div id="acct-addresses-list"></div>
      </div><!-- /addresses tab -->

    </div><!-- /account-main -->
  </div><!-- /account-layout -->
</div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
// ── Account Page Logic ────────────────────────────────────
let acctUser = null;
let acctAddresses = [];

function switchTab(name) {
  document.querySelectorAll('.account-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.account-nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('tab-' + name)?.classList.add('active');
  document.getElementById('nav-' + name)?.classList.add('active');
}

async function loadAccountPage(user) {
  if (!user) {
    document.getElementById('not-logged-msg').style.display = 'block';
    return;
  }
  acctUser = user;
  document.getElementById('account-layout').style.display = 'flex';
  document.getElementById('not-logged-msg').style.display = 'none';

  // Fill profile
  document.getElementById('pf-name').textContent  = user.nome  || '—';
  document.getElementById('pf-email').textContent  = user.email || '—';
  document.getElementById('pf-phone').textContent  = fmtPhone(user.telefone) || '—';
  document.getElementById('pf-cpf').textContent    = fmtCpf(user.cpf_cnpj)  || '—';
  // Pre-fill edit form
  document.getElementById('edit-name').value  = user.nome  || '';
  document.getElementById('edit-email').value = user.email || '';
  document.getElementById('edit-phone').value = fmtPhone(user.telefone) || '';
  document.getElementById('edit-cpf').value   = fmtCpf(user.cpf_cnpj)  || '';

  if (user.is_admin) document.getElementById('nav-admin').style.display = 'flex';

  await handleMpReturn();

  // Load addresses and orders
  await loadAcctAddresses();
  await loadAcctOrders();
}

async function handleMpReturn() {
  if (window.APP_LOCAL_MODE) return;
  const params = new URLSearchParams(window.location.search || '');
  const status = (params.get('status') || params.get('collection_status') || '').toLowerCase();
  const paymentId = params.get('payment_id') || params.get('collection_id') || '';
  const externalRef = params.get('external_reference') || '';
  if (!paymentId || !externalRef) return;
  if (status !== 'approved') return;

  try {
    const res = await fetch('/conta.php?action=mp-confirm', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.APP_CSRF_TOKEN || ''
      },
      body: JSON.stringify({ payment_id: paymentId, external_reference: externalRef })
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) return;

    const msg = document.getElementById('payment-success-msg');
    if (msg) msg.style.display = 'block';
    switchTab('orders');
    await loadAcctOrders();

    const nextUrl = window.location.pathname;
    window.history.replaceState({}, document.title, nextUrl);
  } catch (e) {
    return;
  }
}

function fmtPhone(v) {
  if (!v) return '';
  const d = String(v).replace(/\D/g,'');
  if (d.length === 11) return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
  if (d.length === 10) return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
  return v;
}

function fmtCpf(v) {
  if (!v) return '';
  const d = String(v).replace(/\D/g,'');
  if (d.length === 11) return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}`;
  return v;
}

async function loadAcctOrders() {
  const list = document.getElementById('acct-orders-list');
  if (!list) return;

  if (window.APP_LOCAL_MODE) {
    list.innerHTML = `<div class="addr-empty"><p>Pedidos não disponíveis no modo local.</p></div>`;
    return;
  }

  const sb = getSB();
  const { data: { session } } = await sb.auth.getSession();
  if (!session) return;

  const { data: pedidos, error } = await sb
    .from('pedido')
    .select('id, status, vlr_total, vlr_frete, metodo_pagamento, dt_pedido, pedido_item(codprod, quantidade, vlr_unitario)')
    .eq('cliente_id', session.user.id)
    .order('dt_pedido', { ascending: false });

  if (error || !pedidos || !pedidos.length) {
    list.innerHTML = `<div class="addr-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#d1d5db"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      <p>Você ainda não fez nenhum pedido.</p>
      <a href="/products.php" style="color:#ca8a04;font-weight:600;">Ver produtos →</a>
    </div>`;
    return;
  }

  const statusLabel = { pendente:'Aguardando', pago:'Pago', enviado:'Enviado', entregue:'Entregue', cancelado:'Cancelado' };
  const statusColor = { pendente:'#ca8a04', pago:'#16a34a', enviado:'#2563eb', entregue:'#16a34a', cancelado:'#ef4444' };
  const pgtoLabel   = { mercadopago:'Mercado Pago', pix:'PIX', cartao:'Cartão de Crédito' };

  list.innerHTML = pedidos.map(p => {
    const dt = p.dt_pedido ? new Date(p.dt_pedido).toLocaleDateString('pt-BR') : '—';
    const itensQtd = (p.pedido_item || []).reduce((s, i) => s + Number(i.quantidade), 0);
    const cor   = statusColor[p.status] || '#6b7280';
    const label = statusLabel[p.status] || p.status;
    return `
      <div class="addr-card" style="margin-bottom:.75rem;">
        <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:.5rem;">
          <div>
            <div style="font-weight:700;color:#111827;margin-bottom:.25rem;">Pedido #${p.id}</div>
            <div style="font-size:.8rem;color:#6b7280;">${dt} · ${itensQtd} item(ns) · ${pgtoLabel[p.metodo_pagamento] || p.metodo_pagamento || '—'}</div>
            <div style="font-size:.8rem;color:#6b7280;">Frete: ${fmtBRL(p.vlr_frete || 0)}</div>
          </div>
          <div style="text-align:right;">
            <span style="display:inline-block;padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:700;background:${cor}1a;color:${cor};">${label}</span>
            <div style="font-weight:700;font-size:1rem;color:#111827;margin-top:.375rem;">${fmtBRL(p.vlr_total)}</div>
          </div>
        </div>
      </div>`;
  }).join('');
}

async function loadAcctAddresses() {
  const sb = getSB();
  const { data: { session } } = await sb.auth.getSession();
  if (!session) return;
  const { data } = await sb.from('endereco').select('*').eq('cliente_id', session.user.id).order('is_padrao', { ascending: false });
  acctAddresses = data || [];
  renderAcctAddresses();
}

function addrFormHTML(addr) {
  return `
    <div class="addr-edit-form" id="edit-form-${addr.id}" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid #e5e7eb;">
      <div class="alert alert-error" id="edit-err-${addr.id}" style="display:none;margin-bottom:.75rem;"></div>
      <div class="form-grid" style="margin-bottom:.75rem;">
        <div class="form-field"><label class="form-label">CEP *</label>
          <input type="text" name="cep" class="input-field" maxlength="9" inputmode="numeric" data-cep-input value="${escHtml(addr.cep)}"></div>
        <div class="form-field col-span-2"><label class="form-label">Logradouro *</label>
          <input type="text" name="logradouro" class="input-field" data-field="logradouro" value="${escHtml(addr.logradouro)}"></div>
        <div class="form-field"><label class="form-label">Número *</label>
          <input type="text" name="numero" class="input-field" value="${escHtml(addr.numero)}"></div>
        <div class="form-field"><label class="form-label">Complemento</label>
          <input type="text" name="complemento" class="input-field" value="${escHtml(addr.complemento || '')}"></div>
        <div class="form-field"><label class="form-label">Bairro</label>
          <input type="text" name="bairro" class="input-field" data-field="bairro" value="${escHtml(addr.bairro)}"></div>
        <div class="form-field"><label class="form-label">Cidade</label>
          <input type="text" name="cidade" class="input-field" data-field="cidade" value="${escHtml(addr.cidade)}"></div>
        <div class="form-field"><label class="form-label">UF</label>
          <input type="text" name="uf" class="input-field" maxlength="2" style="text-transform:uppercase;" data-field="uf" value="${escHtml(addr.uf)}"></div>
      </div>
      <div style="display:flex;gap:.5rem;">
        <button class="btn btn-primary btn-sm" onclick="saveEditAddr(${addr.id})">Salvar</button>
        <button class="btn btn-outline btn-sm" onclick="cancelEditAddr(${addr.id})">Cancelar</button>
      </div>
    </div>`;
}

function renderAcctAddresses() {
  const list = document.getElementById('acct-addresses-list');
  if (!acctAddresses.length) {
    list.innerHTML = `<div class="addr-empty"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#d1d5db"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg><p>Nenhum endereço cadastrado</p><a href="#" onclick="document.getElementById('acct-new-addr-form').style.display='block'" style="color:#ca8a04;font-weight:600;">+ Adicionar meu primeiro endereço</a></div>`;
    return;
  }
  list.innerHTML = acctAddresses.map(addr => `
    <div class="addr-card${addr.is_padrao ? ' default' : ''}" id="addr-${addr.id}">
      <div style="display:flex;justify-content:space-between;align-items:start;">
        <div>
          ${addr.is_padrao ? '<span class="badge badge-yellow" style="margin-bottom:.5rem;">✓ Padrão</span>' : ''}
          <div style="font-size:.875rem;color:#374151;margin-top:.25rem;">${escHtml(addr.logradouro)}, ${escHtml(addr.numero)}</div>
          ${addr.complemento ? `<div style="font-size:.8rem;color:#6b7280;">${escHtml(addr.complemento)}</div>` : ''}
          <div style="font-size:.8rem;color:#6b7280;">${escHtml(addr.bairro)} — ${escHtml(addr.cidade)}/${escHtml(addr.uf)}</div>
          <div style="font-size:.8rem;color:#6b7280;">CEP: ${escHtml(addr.cep)}</div>
        </div>
        <div class="addr-card-actions">
          ${!addr.is_padrao ? `<button class="addr-action-btn star" onclick="setDefaultAddr(${addr.id})" title="Definir como padrão"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></button>` : ''}
          <button class="addr-action-btn edit" onclick="toggleEditAddr(${addr.id})" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
          <button class="addr-action-btn trash" onclick="showDeleteConfirm(${addr.id})" title="Excluir"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
        </div>
      </div>
      ${addrFormHTML(addr)}
      <div class="addr-delete-confirm" id="del-${addr.id}">
        <span style="font-size:.875rem;color:#ef4444;font-weight:500;">Excluir este endereço?</span>
        <div style="display:flex;gap:.5rem;">
          <button class="btn btn-danger btn-sm" onclick="deleteAddr(${addr.id})">Sim, excluir</button>
          <button class="btn btn-outline btn-sm" onclick="document.getElementById('del-${addr.id}').classList.remove('show')">Cancelar</button>
        </div>
      </div>
    </div>`).join('');

  // Attach CEP autocomplete to edit forms
  document.querySelectorAll('.addr-edit-form [data-cep-input]').forEach(inp => maskCEP && maskCEP(inp));
}

function toggleEditAddr(id) {
  const form = document.getElementById('edit-form-' + id);
  if (!form) return;
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function cancelEditAddr(id) {
  document.getElementById('edit-form-' + id).style.display = 'none';
  document.getElementById('edit-err-' + id).style.display  = 'none';
}

async function saveEditAddr(id) {
  const form = document.getElementById('edit-form-' + id);
  const errEl = document.getElementById('edit-err-' + id);
  const get = n => form.querySelector(`[name="${n}"]`)?.value.trim() || '';
  const cep = get('cep').replace(/\D/g, '');
  if (!cep || !get('logradouro') || !get('numero')) {
    errEl.textContent = 'Preencha os campos obrigatórios.'; errEl.style.display = 'block'; return;
  }
  const sb = getSB();
  const { error } = await sb.from('endereco').update({
    cep: cep.slice(0,5) + '-' + cep.slice(5),
    logradouro:  get('logradouro'),
    numero:      get('numero'),
    complemento: get('complemento'),
    bairro:      get('bairro'),
    cidade:      get('cidade'),
    uf:          get('uf').toUpperCase().slice(0, 2),
  }).eq('id', id);
  if (error) { errEl.textContent = 'Não foi possível salvar agora. Verifique sua conexão e tente novamente.'; errEl.style.display = 'block'; return; }
  await loadAcctAddresses();
}

async function setDefaultAddr(id) {
  const sb = getSB();
  const { data:{session} } = await sb.auth.getSession();
  if (!session) return;
  await sb.from('endereco').update({ is_padrao: false }).eq('cliente_id', session.user.id);
  await sb.from('endereco').update({ is_padrao: true }).eq('id', id);
  await loadAcctAddresses();
}

function showDeleteConfirm(id) {
  document.getElementById('del-' + id)?.classList.add('show');
}

async function deleteAddr(id) {
  const sb = getSB();
  await sb.from('endereco').delete().eq('id', id);
  await loadAcctAddresses();
}

// Edit profile
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('edit-profile-btn')?.addEventListener('click', () => {
    document.getElementById('profile-view').style.display = 'none';
    document.getElementById('profile-edit').style.display = 'block';
    maskPhone(document.getElementById('edit-phone'));
  });
  document.getElementById('cancel-profile-btn')?.addEventListener('click', () => {
    document.getElementById('profile-view').style.display = '';
    document.getElementById('profile-edit').style.display = 'none';
  });
  document.getElementById('save-profile-btn')?.addEventListener('click', async () => {
    const sb = getSB();
    const { data:{session} } = await sb.auth.getSession();
    if (!session) return;
    const nome = document.getElementById('edit-name').value.trim();
    const telefone = document.getElementById('edit-phone').value.replace(/\D/g,'');
    const errEl = document.getElementById('profile-error');
    const okEl  = document.getElementById('profile-success');
    const { error } = await sb.from('cliente').update({ nome, telefone }).eq('id', session.user.id);
    if (error) { errEl.textContent = 'Não foi possível salvar agora. Verifique sua conexão e tente novamente.'; errEl.style.display = 'block'; return; }
    document.getElementById('pf-name').textContent  = nome;
    document.getElementById('pf-phone').textContent = fmtPhone(telefone);
    okEl.style.display = 'block';
    setTimeout(() => { okEl.style.display = 'none'; }, 3000);
    document.getElementById('profile-view').style.display = '';
    document.getElementById('profile-edit').style.display = 'none';
  });

  // New address toggle
  document.getElementById('new-addr-toggle-btn')?.addEventListener('click', () => {
    const f = document.getElementById('acct-new-addr-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
  });
  document.getElementById('acct-cancel-addr')?.addEventListener('click', () => {
    document.getElementById('acct-new-addr-form').style.display = 'none';
  });
  document.getElementById('acct-save-addr')?.addEventListener('click', async () => {
    const sb = getSB();
    const { data:{session} } = await sb.auth.getSession();
    if (!session) return;
    const form = document.getElementById('acct-new-addr-form');
    const get = n => form.querySelector(`[name="${n}"]`)?.value.trim() || '';
    const cep = get('cep').replace(/\D/g,'');
    if (!cep || !get('logradouro') || !get('numero')) {
      const e = document.getElementById('acct-addr-error');
      e.textContent = 'Preencha os campos obrigatórios.'; e.style.display='block'; return;
    }
    const isFirst = acctAddresses.length === 0;
    const { error } = await sb.from('endereco').insert({
      cliente_id: session.user.id,
      cep: cep.slice(0,5)+'-'+cep.slice(5),
      logradouro: get('logradouro'), numero: get('numero'),
      complemento: get('complemento'), bairro: get('bairro'),
      cidade: get('cidade'), uf: get('uf').toUpperCase().slice(0,2),
      is_padrao: isFirst,
    });
    if (error) { const e = document.getElementById('acct-addr-error'); e.textContent='Não foi possível salvar o endereço. Verifique sua conexão e tente novamente.'; e.style.display='block'; return; }
    document.getElementById('acct-new-addr-form').style.display = 'none';
    await loadAcctAddresses();
  });
});
</script>
</body>
</html>
