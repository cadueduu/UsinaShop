<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Finalizar Compra';
$categories = fetch_categories(false);
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>

<main class="page-content">
<div class="container" style="max-width:64rem;padding:2.5rem 1rem 5rem;">

  <!-- Page header -->
  <div style="margin-bottom:2rem;">
    <h1 class="page-title" style="font-size:1.875rem;">Finalizar Compra</h1>
    <p style="color:#6b7280;margin-top:.5rem;padding-left:.875rem;font-size:.875rem;">
      Selecione ou cadastre o endereço de entrega.
    </p>
  </div>

  <!-- Auth guard message (shown by JS if not logged in) -->
  <div id="checkout-auth-msg" class="alert alert-info" style="display:none;">
    <strong>Faça login</strong> para continuar com a compra.
    <a href="/login.php" style="font-weight:700;text-decoration:underline;margin-left:.5rem;">Entrar / Cadastrar</a>
  </div>

  <!-- Main checkout layout -->
  <div id="checkout-main" class="checkout-layout" style="display:none;">

    <!-- ── Left: Addresses ── -->
    <div class="checkout-main">
      <h2 style="font-size:1.125rem;font-weight:700;margin-bottom:1rem;">Endereço de Entrega</h2>

      <!-- Saved addresses (populated by JS) -->
      <div id="addresses-list"></div>

      <!-- Add new address button -->
      <button id="toggle-new-addr" class="add-address-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m-8-8h16"/></svg>
        Adicionar novo endereço
      </button>

      <!-- New address form -->
      <div id="new-addr-form" class="checkout-form" style="display:none;" data-address-form>
        <h3 class="checkout-form-title">Novo Endereço</h3>
        <div class="alert alert-error" id="addr-form-error" style="display:none;"></div>
        <div class="form-grid">
          <div class="form-field">
            <label class="form-label">CEP *</label>
            <input type="text" name="cep" class="input-field" placeholder="00000-000" maxlength="9" data-cep-input inputmode="numeric" required>
          </div>
          <div class="form-field col-span-2">
            <label class="form-label">Logradouro *</label>
            <input type="text" name="logradouro" class="input-field" placeholder="Rua, Avenida..." data-field="logradouro" required>
          </div>
          <div class="form-field">
            <label class="form-label">Número *</label>
            <input type="text" name="numero" class="input-field" placeholder="123" required>
          </div>
          <div class="form-field">
            <label class="form-label">Complemento</label>
            <input type="text" name="complemento" class="input-field" placeholder="Apto, Bloco...">
          </div>
          <div class="form-field">
            <label class="form-label">Bairro *</label>
            <input type="text" name="bairro" class="input-field" data-field="bairro" required>
          </div>
          <div class="form-field">
            <label class="form-label">Cidade *</label>
            <input type="text" name="cidade" class="input-field" data-field="cidade" required>
          </div>
          <div class="form-field">
            <label class="form-label">UF *</label>
            <input type="text" name="uf" class="input-field" placeholder="MG" maxlength="2" style="text-transform:uppercase;" data-field="uf" required>
          </div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem;">
          <button type="button" id="save-new-addr" class="btn btn-primary">Salvar Endereço</button>
          <button type="button" id="cancel-new-addr" class="btn btn-outline">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- ── Right: Order Summary ── -->
    <div class="checkout-summary">
      <div class="summary-card">
        <div style="display:flex;align-items:center;gap:.5rem;font-weight:700;font-size:1.125rem;margin-bottom:1rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
          Resumo do Pedido
        </div>
        <div class="summary-items" id="summary-items"></div>
        <div class="summary-total">
          <span class="summary-total-label">Total</span>
          <span class="summary-total-val" id="summary-total">R$ 0,00</span>
        </div>
        <!-- Selected address preview -->
        <div id="summary-address" class="summary-address-box" style="display:none;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#ca8a04" style="flex-shrink:0;margin-top:.125rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span id="summary-address-text"></span>
        </div>
        <button id="continue-payment-btn" class="continue-btn" disabled>
          Continuar para Pagamento →
        </button>
      </div>
    </div>

  </div><!-- /checkout-main -->
</div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
// ── Checkout page logic ──────────────────────────────────
let selectedAddrId = null;
let checkoutAddresses = [];

async function loadCheckoutAddresses() {
  if (window.APP_LOCAL_MODE) {
    let u = null;
    try { u = JSON.parse(localStorage.getItem('spark_local_user') || 'null'); } catch(e) { u = null; }
    if (!u) {
      document.getElementById('checkout-auth-msg').style.display = 'block';
      return;
    }

    document.getElementById('checkout-main').style.display = 'flex';

    const items = Cart.getAll();
    if (items.length === 0) { window.location.href = '/'; return; }

    const summaryList = document.getElementById('summary-items');
    summaryList.innerHTML = items.map(it =>
      `<div class="summary-item"><span class="summary-item-name">${escHtml(it.name)} ×${it.qty}</span><span class="summary-item-val">${fmtBRL(it.price * it.qty)}</span></div>`
    ).join('');
    document.getElementById('summary-total').textContent = fmtBRL(Cart.total());

    let addrs = [];
    try { addrs = JSON.parse(localStorage.getItem('spark_local_addresses') || '[]'); } catch(e) { addrs = []; }
    checkoutAddresses = Array.isArray(addrs) ? addrs : [];
    renderAddresses(checkoutAddresses);

    const def = checkoutAddresses.find(a => a.is_padrao);
    if (def) selectAddress(def.id);
    return;
  }

  const sb = getSB();
  if (!sb) return;
  const { data: { session } } = await sb.auth.getSession();
  if (!session) {
    document.getElementById('checkout-auth-msg').style.display = 'block';
    return;
  }

  // Garante que o carrinho está sincronizado antes de checar
  await syncCartFromDB(session.user.id);

  const items = Cart.getAll();
  if (items.length === 0) { window.location.href = '/'; return; }

  document.getElementById('checkout-main').style.display = 'flex';

  const summaryList = document.getElementById('summary-items');
  summaryList.innerHTML = items.map(it =>
    `<div class="summary-item"><span class="summary-item-name">${escHtml(it.name)} ×${it.qty}</span><span class="summary-item-val">${fmtBRL(it.price * it.qty)}</span></div>`
  ).join('');
  document.getElementById('summary-total').textContent = fmtBRL(Cart.total());

  // Fetch addresses
  const { data: addrs } = await sb.from('endereco').select('*').eq('cliente_id', session.user.id).order('is_padrao', { ascending: false });
  checkoutAddresses = addrs || [];
  renderAddresses(checkoutAddresses);

  // Auto-select default
  const def = checkoutAddresses.find(a => a.is_padrao);
  if (def) selectAddress(def.id);
}

function renderAddresses(addrs) {
  const list = document.getElementById('addresses-list');
  if (!addrs || addrs.length === 0) {
    list.innerHTML = '';
    return;
  }
  list.innerHTML = addrs.map(addr => `
    <div class="address-card${selectedAddrId === addr.id ? ' selected' : ''}" data-addr-id="${addr.id}">
      <div class="address-card-header" onclick="selectAddress(${addr.id})" style="cursor:pointer;">
        <div class="address-radio">
          <div class="address-radio-dot"></div>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:.875rem;">${escHtml(addr.logradouro)}, ${escHtml(addr.numero)}</div>
          ${addr.complemento ? `<div style="font-size:.8rem;color:#6b7280;">${escHtml(addr.complemento)}</div>` : ''}
          <div style="font-size:.8rem;color:#6b7280;">${escHtml(addr.bairro)} — ${escHtml(addr.cidade)}/${escHtml(addr.uf)}</div>
          <div style="font-size:.8rem;color:#6b7280;">CEP: ${escHtml(addr.cep)}</div>
        </div>
        <div style="display:flex;gap:.25rem;flex-shrink:0;align-items:center;">
          ${addr.is_padrao ? '<span class="badge badge-yellow">✓ Padrão</span>' : ''}
          <button class="addr-action-btn edit" onclick="event.stopPropagation();toggleCheckoutEditAddr(${addr.id})" title="Editar" style="padding:.25rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          </button>
        </div>
      </div>
      <!-- Inline edit form -->
      <div id="co-edit-${addr.id}" style="display:none;padding-top:.75rem;margin-top:.75rem;border-top:1px solid #e5e7eb;">
        <div class="alert alert-error" id="co-err-${addr.id}" style="display:none;margin-bottom:.5rem;"></div>
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
          <button class="btn btn-primary btn-sm" onclick="saveCheckoutEditAddr(${addr.id})">Salvar</button>
          <button class="btn btn-outline btn-sm" onclick="toggleCheckoutEditAddr(${addr.id})">Cancelar</button>
        </div>
      </div>
    </div>`).join('');
}

function toggleCheckoutEditAddr(id) {
  const form = document.getElementById('co-edit-' + id);
  if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

async function saveCheckoutEditAddr(id) {
  const form = document.getElementById('co-edit-' + id);
  const errEl = document.getElementById('co-err-' + id);
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
  if (error) { errEl.textContent = 'Não foi possível salvar o endereço. Verifique sua conexão e tente novamente.'; errEl.style.display = 'block'; return; }
  // Reload addresses and keep selection
  const { data: { session } } = await sb.auth.getSession();
  const { data: addrs } = await sb.from('endereco').select('*').eq('cliente_id', session.user.id).order('is_padrao', { ascending: false });
  checkoutAddresses = addrs || [];
  renderAddresses(checkoutAddresses);
  if (selectedAddrId) selectAddress(selectedAddrId);
}

function selectAddress(id) {
  selectedAddrId = id;
  const addr = checkoutAddresses.find(a => a.id === id);
  // Update cards
  document.querySelectorAll('.address-card').forEach(el => {
    el.classList.toggle('selected', parseInt(el.dataset.addrId) === id);
  });
  // Update summary
  if (addr) {
    document.getElementById('summary-address').style.display = 'flex';
    document.getElementById('summary-address-text').textContent = `${addr.logradouro}, ${addr.numero} — ${addr.cidade}/${addr.uf}`;
  }
  document.getElementById('continue-payment-btn').disabled = false;
}

document.addEventListener('DOMContentLoaded', () => {
  loadCheckoutAddresses();

  // Toggle new address form
  document.getElementById('toggle-new-addr')?.addEventListener('click', () => {
    const f = document.getElementById('new-addr-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
  });
  document.getElementById('cancel-new-addr')?.addEventListener('click', () => {
    document.getElementById('new-addr-form').style.display = 'none';
  });

  // Save new address
  document.getElementById('save-new-addr')?.addEventListener('click', async () => {
    const form = document.getElementById('new-addr-form');
    const get = n => form.querySelector(`[name="${n}"]`)?.value.trim() || '';
    const cep = get('cep').replace(/\D/g,'');
    const logradouro = get('logradouro');
    const numero     = get('numero');
    if (!cep || !logradouro || !numero) {
      document.getElementById('addr-form-error').textContent = 'Preencha os campos obrigatórios.';
      document.getElementById('addr-form-error').style.display = 'block';
      return;
    }
    const cepFmt = cep.slice(0,5) + '-' + cep.slice(5);
    const isFirst = checkoutAddresses.length === 0;

    if (window.APP_LOCAL_MODE) {
      const maxId = checkoutAddresses.reduce((m, a) => Math.max(m, parseInt(a.id, 10) || 0), 0);
      const newAddr = {
        id: maxId + 1,
        cep: cepFmt,
        logradouro,
        numero,
        complemento: get('complemento'),
        bairro: get('bairro'),
        cidade: get('cidade'),
        uf: get('uf').toUpperCase().slice(0,2),
        is_padrao: isFirst,
      };
      if (newAddr.is_padrao) {
        checkoutAddresses = checkoutAddresses.map(a => ({ ...a, is_padrao: false }));
      }
      checkoutAddresses.push(newAddr);
      localStorage.setItem('spark_local_addresses', JSON.stringify(checkoutAddresses));
      renderAddresses(checkoutAddresses);
      selectAddress(newAddr.id);
      document.getElementById('new-addr-form').style.display = 'none';
      return;
    }

    const sb = getSB();
    const { data: { session } } = await sb.auth.getSession();
    if (!session) return;
    const { data: newAddr, error } = await sb.from('endereco').insert({
      cliente_id: session.user.id,
      cep: cepFmt,
      logradouro,
      numero,
      complemento: get('complemento'),
      bairro: get('bairro'),
      cidade: get('cidade'),
      uf: get('uf').toUpperCase().slice(0,2),
      is_padrao: isFirst,
    }).select().single();
    if (error) {
      document.getElementById('addr-form-error').textContent = 'Erro ao salvar endereço.';
      document.getElementById('addr-form-error').style.display = 'block';
      return;
    }
    checkoutAddresses.push(newAddr);
    renderAddresses(checkoutAddresses);
    selectAddress(newAddr.id);
    document.getElementById('new-addr-form').style.display = 'none';
  });

  // Continue to payment
  document.getElementById('continue-payment-btn')?.addEventListener('click', async () => {
    if (!selectedAddrId) return;
    try {
      if (window.flushAllPendingCartUpserts) {
        await window.flushAllPendingCartUpserts();
      }
    } catch (_) {}
    window.location.href = '/checkout-pagamento.php?endereco=' + selectedAddrId;
  });
});
</script>
</body>
</html>
