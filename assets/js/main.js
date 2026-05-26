/* ============================================================
   SPARK ELETRÔNICA — Main JavaScript
   Handles: Auth, Cart, Sidebars, UI Interactions
   ============================================================ */

'use strict';

// ─── CONFIG (injetado pelo PHP via head.php — não há credenciais neste arquivo) ─

function storageGet(key, fallback) {
  try {
    const v = localStorage.getItem(key);
    return v === null ? fallback : v;
  } catch (e) {
    return fallback;
  }
}

function storageSet(key, value) {
  try {
    localStorage.setItem(key, value);
    return true;
  } catch (e) {
    return false;
  }
}

// ─── SUPABASE CLIENT (lazy init) ──────────────────────────
let _sbClient = null;
function getSB() {
  if (!_sbClient && window.supabase && window.APP_SB_URL && window.APP_SB_ANON) {
    _sbClient = window.supabase.createClient(window.APP_SB_URL, window.APP_SB_ANON);
  }
  return _sbClient;
}

// ─── CART STORE (somente banco — sem localStorage) ────────
const Cart = (() => {
  let items = []; // memória de sessão apenas

  function count()    { return items.reduce((s, i) => s + (parseInt(i.qty, 10) || 0), 0); }
  function total()    { return items.reduce((s, i) => s + (Number(i.price) * (parseInt(i.qty, 10) || 0)), 0); }
  function getAll()   { return [...items]; }
  function setItems(newItems) { items = newItems; updateCartUI(); renderCartItems(); }

  function add(item) {
    if (!window.APP_LOCAL_MODE && !UserStore.isLoggedIn()) {
      window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
      return;
    }
    const existing = items.find(i => i.id === item.id);
    const newQty = (parseInt(existing?.qty, 10) || 0) + (parseInt(item.qty, 10) || 1);
    const peso = parseFloat(item.peso || existing?.peso || 0.3);
    if (existing) { existing.qty = newQty; existing.peso = peso; }
    else { items.push({ id: item.id, name: item.name, price: item.price, image: item.image, qty: item.qty || 1, peso }); }
    updateCartUI();
    CartSidebar.open();
    if (!window.APP_LOCAL_MODE) upsertCartItemDB(item.id, newQty, peso);
  }

  function remove(id) {
    items = items.filter(i => i.id !== id);
    updateCartUI();
    renderCartItems();
    if (!window.APP_LOCAL_MODE) upsertCartItemDB(id, 0, 0);
  }

  function setQty(id, qty) {
    const newQty = Math.max(1, qty);
    const item = items.find(i => i.id === id);
    if (item) { item.qty = newQty; }
    updateCartUI();
    renderCartItems();
    if (!window.APP_LOCAL_MODE) upsertCartItemDB(id, newQty, item?.peso || 0.3);
  }

  function clear() {
    items = [];
    updateCartUI();
    renderCartItems();
    // Limpeza no DB feita por finalizarCompra após criar o pedido
  }

  return { add, remove, setQty, clear, count, total, getAll, setItems };
})();

// ─── CART ↔ DB SYNC ──────────────────────────────────────
async function syncCartFromDB(userId) {
  const sb = getSB();
  if (!sb || !userId) return;
  try {
    // Carrega carrinho do banco com dados completos do produto
    const { data } = await sb
      .from('carrinho')
      .select('codprod, quantidade, peso_total, produto(comnome, descrprod, peso, altura, largura, comprimento, codprodemb, embalagem:codprodemb(peso, altura, largura, comprimento), preco(vlr_venda))')
      .eq('cliente_id', userId);

    if (!data) return;

    const ids = Array.from(new Set((data || []).map(r => r?.codprod).filter(Boolean)));
    let imageMap = {};
    try {
      const res = await fetch(`/checkout-pagamento.php?action=product-images&ids=${encodeURIComponent(ids.join(','))}`);
      const json = await res.json().catch(() => null);
      if (res.ok && json?.ok && json?.images && typeof json.images === 'object') {
        imageMap = json.images;
      }
    } catch (e) {
      imageMap = {};
    }

    const cartItems = data.map(row => {
      const prod   = row.produto || {};
      const emb    = prod.embalagem || {};
      const name   = prod.comnome || prod.descrprod || '#' + row.codprod;
      const price  = prod.preco?.[0]?.vlr_venda ?? 0;
      const image  = imageMap?.[String(row.codprod)] || '/assets/images/produtos/logo.png';
      const peso   = parseFloat(prod.peso || 0.3);
      return {
        id: row.codprod, name, price, image, qty: row.quantidade,
        peso,
        pesoTotal:   parseFloat(row.peso_total || (peso * row.quantidade).toFixed(3)),
        altura:      parseInt(prod.altura      || 10),
        largura:     parseInt(prod.largura     || 15),
        comprimento: parseInt(prod.comprimento || 20),
        embAltura:   parseInt(emb.altura       || prod.altura      || 10),
        embLargura:  parseInt(emb.largura      || prod.largura     || 15),
        embComprimento: parseInt(emb.comprimento || prod.comprimento || 20),
      };
    });

    Cart.setItems(cartItems);
  } catch(e) { console.error('Erro ao carregar carrinho:', e); }
}

const _cartUpsertTimers = {};
function upsertCartItemDB(id, qty, pesoUnitario = 0.3) {
  clearTimeout(_cartUpsertTimers[id]);
  _cartUpsertTimers[id] = setTimeout(() => _flushCartUpsert(id, qty), 600);
}

async function _flushCartUpsert(id, qty) {
  const sb = getSB();
  if (!sb) return;
  try {
    const { data: { session } } = await sb.auth.getSession();
    if (!session) return;
    if (qty <= 0) {
      await sb.from('carrinho').delete().eq('cliente_id', session.user.id).eq('codprod', id);
    } else {
      // peso_total é calculado automaticamente pelo trigger no banco (produto.peso × quantidade)
      await sb.from('carrinho').upsert(
        { cliente_id: session.user.id, codprod: id, quantidade: qty },
        { onConflict: 'cliente_id,codprod' }
      );
    }
  } catch(e) { console.error('Erro ao sincronizar carrinho:', e); }
}

// ─── CART UI ──────────────────────────────────────────────
function updateCartUI() {
  const n = Cart.count();
  // Desktop badge
  document.querySelectorAll('.cart-badge').forEach(el => {
    el.textContent = n;
    el.classList.toggle('visible', n > 0);
  });
  // Mobile badge
  document.querySelectorAll('.mobile-cart-badge').forEach(el => {
    el.textContent = n;
    el.classList.toggle('visible', n > 0);
  });
}

function fmtBRL(v) {
  return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 });
}

function renderCartItems() {
  const body  = document.getElementById('cart-body');
  const foot  = document.getElementById('cart-footer');
  if (!body) return;

  const items = Cart.getAll();
  if (items.length === 0) {
    body.innerHTML = `
      <div class="cart-empty">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <p>Seu carrinho está vazio.</p>
        <a href="/products.php">Continuar comprando</a>
      </div>`;
    if (foot) foot.style.display = 'none';
    return;
  }

  body.innerHTML = items.map(item => `
    <div class="cart-item" data-id="${item.id}">
      <div class="cart-item__img">
        <img src="${item.image}" alt="${escHtml(item.name)}" onerror="this.src='/assets/images/produtos/logo.png'">
      </div>
      <div class="cart-item__info">
        <div class="cart-item__name">${escHtml(item.name)}</div>
        <div class="cart-item__price">${fmtBRL(item.price)}</div>
        <div class="cart-item__actions">
          <div class="qty-control">
            <button class="qty-btn" onclick="Cart.setQty(${item.id}, ${item.qty - 1})" aria-label="Diminuir">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"/></svg>
            </button>
            <span class="qty-val">${item.qty}</span>
            <button class="qty-btn" onclick="Cart.setQty(${item.id}, ${item.qty + 1})" aria-label="Aumentar">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14"/></svg>
            </button>
          </div>
          <button class="cart-remove" onclick="Cart.remove(${item.id})" aria-label="Remover">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
          </button>
        </div>
      </div>
    </div>`).join('');

  if (foot) {
    foot.style.display = 'block';
    const subtotalEl = document.getElementById('cart-subtotal');
    if (subtotalEl) subtotalEl.textContent = fmtBRL(Cart.total());
  }
}

// ─── CART SIDEBAR ─────────────────────────────────────────
const CartSidebar = {
  lastFocus: null,
  open()  {
    const sidebar = document.getElementById('cart-sidebar');
    if (!sidebar) return;
    this.lastFocus = document.activeElement;
    sidebar.classList.add('open');
    sidebar.setAttribute('aria-hidden', 'false');
    overlay(true);
    renderCartItems();
    setTimeout(() => {
      const closeBtn = document.getElementById('cart-close-btn');
      (closeBtn || sidebar).focus?.();
    }, 0);
  },
  close() {
    const sidebar = document.getElementById('cart-sidebar');
    if (!sidebar) return;
    sidebar.classList.remove('open');
    sidebar.setAttribute('aria-hidden', 'true');
    overlay(false);
    const el = this.lastFocus;
    this.lastFocus = null;
    el?.focus?.();
  },
  toggle(){ document.getElementById('cart-sidebar')?.classList.contains('open') ? this.close() : this.open(); }
};

// ─── SEARCH SIDEBAR ───────────────────────────────────────
const SearchSidebar = {
  lastFocus: null,
  open()  {
    const sidebar = document.getElementById('search-sidebar');
    if (!sidebar) return;
    this.lastFocus = document.activeElement;
    sidebar.classList.add('open');
    sidebar.setAttribute('aria-hidden', 'false');
    overlay(true);
    loadSearchCats();
    setTimeout(() => {
      const input = document.getElementById('search-input');
      (input || sidebar).focus?.();
    }, 0);
  },
  close() {
    const sidebar = document.getElementById('search-sidebar');
    if (!sidebar) return;
    sidebar.classList.remove('open');
    sidebar.setAttribute('aria-hidden', 'true');
    overlay(false);
    const el = this.lastFocus;
    this.lastFocus = null;
    el?.focus?.();
  },
  toggle(){ document.getElementById('search-sidebar')?.classList.contains('open') ? this.close() : this.open(); }
};

async function loadSearchCats() {
  const list = document.getElementById('search-cat-list');
  if (!list || list.dataset.loaded) return;
  list.innerHTML = Array(6).fill('<div class="skeleton" style="height:2.75rem;border-radius:.5rem;margin-bottom:.5rem;"></div>').join('');
  try {
    const res = await fetch('/api/categorias.php');
    const cats = await res.json();
    const roots = [3000000, 4000000];
    const level1 = cats.filter(c => roots.includes(c.codgrupopai));
    list.innerHTML = `<a href="/products.php" class="search-cat-item">Todos os Produtos</a>` +
      level1.map(c => `<a href="/products.php?categoria=${c.codgrupoprod}" class="search-cat-item">${fmtCatName(c.descr_grupo)}<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></a>`).join('');
    list.dataset.loaded = '1';
  } catch(e) { list.innerHTML = '<p class="text-sm text-gray-500">Erro ao carregar categorias.</p>'; }
}

function fmtCatName(name) {
  if (!name) return '';
  return name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
}

// ─── OVERLAY ──────────────────────────────────────────────
function overlay(show) {
  const el = document.getElementById('sidebar-overlay');
  if (el) el.classList.toggle('active', show);
}

// ─── USER STORE (Auth) ────────────────────────────────────
const UserStore = {
  user: null,
  isLoggedIn() { return this.user !== null; },
  async init() {
    if (window.APP_LOCAL_MODE) {
      try {
        const u = JSON.parse(storageGet('spark_local_user', 'null'));
        if (u && typeof u === 'object') {
          this.user = u;
        }
      } catch (e) {
        this.user = null;
      }
      this.updateUI();
      return;
    }
    const sb = getSB();
    if (!sb) return;
    const { data: { session } } = await sb.auth.getSession();
    if (session?.user) {
      await this.loadUser(session.user.id);
      await syncCartFromDB(session.user.id);
    }
    sb.auth.onAuthStateChange(async (event, session) => {
      if (event === 'SIGNED_IN' && session?.user) {
        await this.loadUser(session.user.id);
        await syncCartFromDB(session.user.id);
      } else if (event === 'SIGNED_OUT') {
        this.user = null;
        this.updateUI();
      }
    });
  },
  async loadUser(uid) {
    const sb = getSB();
    const { data } = await sb.from('cliente').select('*').eq('id', uid).single();
    if (data) {
      this.user = { ...data, email: (await sb.auth.getUser()).data.user?.email };
      this.updateUI();
    }
  },
  updateUI() {
    const u = this.user;
    // Desktop header account button
    const btnAcct = document.getElementById('header-account-btn');
    const btnAdmin = document.getElementById('header-admin-btn');
    const mobileAcct = document.getElementById('mobile-account-btn');
    const mobileAdmin = document.getElementById('mobile-admin-btn');

    if (btnAcct) {
      if (u) {
        const firstName = (u.nome || '').split(' ')[0];
        btnAcct.querySelector('.header-action-sublabel').textContent = 'Olá, ' + firstName;
        btnAcct.querySelector('.header-action-label').textContent = 'Minha Conta';
        btnAcct.href = '/conta.php';
      } else {
        btnAcct.querySelector('.header-action-sublabel').textContent = '';
        btnAcct.querySelector('.header-action-label').textContent = 'Entrar';
        btnAcct.href = '/login.php';
      }
    }
    if (btnAdmin) btnAdmin.style.display = (u?.is_admin ? '' : 'none');
    if (mobileAdmin) mobileAdmin.style.display = (u?.is_admin ? '' : 'none');

    // If on conta page, load profile
    if (typeof loadAccountPage === 'function') loadAccountPage(u);
    // If on checkout page, load addresses
    if (typeof loadCheckoutAddresses === 'function') loadCheckoutAddresses();
  },
  async logout() {
    if (window.APP_LOCAL_MODE) {
      storageSet('spark_local_user', 'null');
      this.user = null;
      Cart.clear();
      window.location.href = '/';
      return;
    }
    const sb = getSB();
    await sb.auth.signOut();
    this.user = null;
    Cart.clear(); // limpa memória ao sair
    window.location.href = '/';
  }
};

// ─── HEADER SCROLL EFFECT ─────────────────────────────────
function initHeaderScroll() {
  const header = document.querySelector('.site-header');
  if (!header) return;
  let lastY = 0;
  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    header.classList.toggle('scrolled', y > 10);
    lastY = y;
  }, { passive: true });
}

// ─── BANNER CAROUSEL ──────────────────────────────────────
function initBannerCarousel() {
  const carousel = document.querySelector('.banner-carousel');
  if (!carousel) return;

  const slides   = carousel.querySelectorAll('.banner-slide');
  const dots     = carousel.querySelectorAll('.banner-dot');
  const progress = carousel.querySelector('.banner-progress-bar');
  let current = 0;
  let timer   = null;
  const duration = 5000;

  function goTo(index) {
    slides[current].classList.remove('active');
    slides[current].classList.add('prev');
    dots[current]?.classList.remove('active');
    setTimeout(() => slides.forEach(s => s.classList.remove('prev')), 600);
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current]?.classList.add('active');
    // Lazy-load the <picture> for slides not preloaded — swap data-src(set) to src(set).
    const lazyImg = slides[current].querySelector('img[data-src]');
    if (lazyImg) {
      slides[current].querySelectorAll('source[data-srcset]').forEach(s => {
        s.srcset = s.dataset.srcset;
        delete s.dataset.srcset;
      });
      lazyImg.src = lazyImg.dataset.src;
      delete lazyImg.dataset.src;
    }
    restartProgress();
  }

  function restartProgress() {
    if (!progress) return;
    progress.style.transition = 'none';
    progress.style.width = '0%';
    requestAnimationFrame(() => requestAnimationFrame(() => {
      progress.style.transition = `width ${duration}ms linear`;
      progress.style.width = '100%';
    }));
  }

  function startAuto() {
    stopAuto();
    timer = setInterval(() => goTo(current + 1), duration);
  }
  function stopAuto() { clearInterval(timer); }

  // Arrow buttons
  carousel.querySelector('.banner-arrow-prev')?.addEventListener('click', () => { goTo(current - 1); startAuto(); });
  carousel.querySelector('.banner-arrow-next')?.addEventListener('click', () => { goTo(current + 1); startAuto(); });
  dots.forEach((d, i) => d.addEventListener('click', () => { goTo(i); startAuto(); }));

  // Pause on hover
  carousel.addEventListener('mouseenter', stopAuto);
  carousel.addEventListener('mouseleave', startAuto);

  // Touch swipe
  let touchStart = 0;
  carousel.addEventListener('touchstart', e => { touchStart = e.touches[0].clientX; }, { passive: true });
  carousel.addEventListener('touchend', e => {
    const delta = e.changedTouches[0].clientX - touchStart;
    if (Math.abs(delta) > 50) { goTo(delta < 0 ? current + 1 : current - 1); startAuto(); }
  });

  if (slides.length > 0) {
    slides[0].classList.add('active');
    dots[0]?.classList.add('active');
    restartProgress();
    startAuto();
  }
}

// ─── PRODUCT GALLERY ──────────────────────────────────────
function initProductGallery() {
  const mainImg  = document.getElementById('gallery-main-img');
  const thumbs   = document.querySelectorAll('.gallery-thumb');
  const dots     = document.querySelectorAll('.gallery-dot');
  const prevBtn  = document.querySelector('.gallery-arrow-prev');
  const nextBtn  = document.querySelector('.gallery-arrow-next');
  if (!mainImg || thumbs.length === 0) return;

  // Prefer the higher-res transformation URL the server attached to each thumb
  // (data-gallery-src). Fall back to the thumb's own src for legacy markup.
  const images = Array.from(thumbs).map(t => t.dataset.gallerySrc || t.querySelector('img')?.src || '');
  let current  = 0;
  let timer    = null;

  function goTo(index) {
    current = (index + images.length) % images.length;
    mainImg.style.opacity = '0';
    setTimeout(() => {
      mainImg.src = images[current];
      mainImg.style.opacity = '1';
    }, 150);
    thumbs.forEach((t, i) => t.classList.toggle('active', i === current));
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
  }

  thumbs.forEach((t, i) => t.addEventListener('click', () => { goTo(i); resetTimer(); }));
  dots.forEach((d, i) => d.addEventListener('click', () => { goTo(i); resetTimer(); }));
  prevBtn?.addEventListener('click', () => { goTo(current - 1); resetTimer(); });
  nextBtn?.addEventListener('click', () => { goTo(current + 1); resetTimer(); });

  function startAuto() { timer = setInterval(() => goTo(current + 1), 4000); }
  function resetTimer() { clearInterval(timer); startAuto(); }

  if (images.length > 1) {
    startAuto();
    const wrap = document.querySelector('.gallery-main');
    wrap?.addEventListener('mouseenter', () => clearInterval(timer));
    wrap?.addEventListener('mouseleave', () => resetTimer());
  }
  thumbs[0]?.classList.add('active');
  dots[0]?.classList.add('active');
}

// ─── QTY SELECTOR (product detail) ───────────────────────
function initQtySelector() {
  const selector = document.querySelector('.qty-selector');
  if (!selector) return;
  const valEl = selector.querySelector('.qty-val');
  const minus = selector.querySelector('[data-minus]');
  const plus  = selector.querySelector('[data-plus]');
  if (!valEl) return;

  minus?.addEventListener('click', () => {
    valEl.value = Math.max(1, parseInt(valEl.value) - 1);
    valEl.dispatchEvent(new Event('change'));
  });
  plus?.addEventListener('click', () => {
    valEl.value = parseInt(valEl.value) + 1;
    valEl.dispatchEvent(new Event('change'));
  });
}

// ─── CEP AUTOCOMPLETE ─────────────────────────────────────
async function lookupCep(cepInput) {
  const clean = cepInput.value.replace(/\D/g, '');
  if (clean.length !== 8) return;

  // Find the closest address container (static or dynamic)
  const container = cepInput.closest('form, [data-address-form], .addr-edit-form, [id^="co-edit-"], [id^="edit-form-"]');
  if (!container) return;

  const f = {
    logradouro: container.querySelector('[data-field="logradouro"], [name="logradouro"]'),
    bairro:     container.querySelector('[data-field="bairro"],     [name="bairro"]'),
    cidade:     container.querySelector('[data-field="cidade"],     [name="cidade"]'),
    uf:         container.querySelector('[data-field="uf"],         [name="uf"]'),
    numero:     container.querySelector('[name="numero"]'),
  };

  // Show loading state
  cepInput.style.opacity = '.5';
  cepInput.readOnly = true;

  // Clear a previous error hint
  let hint = container.querySelector('.cep-hint');
  if (hint) hint.remove();

  try {
    const res  = await fetch(`https://viacep.com.br/ws/${clean}/json/`);
    const data = await res.json();

    if (data.erro) {
      showCepHint(cepInput, 'CEP não encontrado.', true);
    } else {
      if (f.logradouro) { f.logradouro.value = data.logradouro || ''; }
      if (f.bairro)     { f.bairro.value     = data.bairro     || ''; }
      if (f.cidade)     { f.cidade.value     = data.localidade || ''; }
      if (f.uf)         { f.uf.value         = data.uf         || ''; }
      // Format CEP and focus numero for quick entry
      cepInput.value = clean.slice(0,5) + '-' + clean.slice(5);
      if (f.numero && !f.numero.value) setTimeout(() => f.numero.focus(), 50);
    }
  } catch(e) {
    showCepHint(cepInput, 'Erro ao consultar CEP.', true);
  } finally {
    cepInput.style.opacity = '';
    cepInput.readOnly = false;
  }
}

function showCepHint(input, msg, isError) {
  const hint = document.createElement('small');
  hint.className = 'cep-hint';
  hint.style.cssText = `display:block;margin-top:.25rem;font-size:.75rem;color:${isError ? '#ef4444' : '#16a34a'};`;
  hint.textContent = msg;
  input.parentNode.appendChild(hint);
  setTimeout(() => hint.remove(), 4000);
}

function initCepInputs() {
  // Mask: format as XXXXX-XXX on input
  function applyMask(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5);
    input.value = v;
  }

  // Event delegation — catches static + dynamically rendered inputs
  document.addEventListener('input', function(e) {
    if (!e.target.matches('[data-cep-input]')) return;
    applyMask(e.target);
    const clean = e.target.value.replace(/\D/g, '');
    if (clean.length === 8) lookupCep(e.target);
  });
}

// ─── PHONE MASK ───────────────────────────────────────────
function maskPhone(input) {
  input.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'');
    if (v.length > 11) v = v.slice(0, 11);
    if (v.length >= 7) {
      this.value = '(' + v.slice(0,2) + ') ' + v.slice(2, v.length <= 10 ? 6 : 7) + '-' + v.slice(v.length <= 10 ? 6 : 7);
    } else if (v.length >= 3) {
      this.value = '(' + v.slice(0,2) + ') ' + v.slice(2);
    } else if (v.length >= 1) {
      this.value = '(' + v;
    }
  });
}

// ─── CPF MASK ─────────────────────────────────────────────
function maskCPF(input) {
  input.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'');
    if (v.length > 11) v = v.slice(0,11);
    if (v.length >= 10) this.value = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6,9)+'-'+v.slice(9);
    else if (v.length >= 7) this.value = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6);
    else if (v.length >= 4) this.value = v.slice(0,3)+'.'+v.slice(3);
    else this.value = v;
  });
}

// ─── ESCAPE HTML ──────────────────────────────────────────
function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── AUTH: LOGIN PAGE ─────────────────────────────────────
function initLoginPage() {
  const loginForm  = document.getElementById('login-form');
  const signupForm = document.getElementById('signup-form');
  const loginTab   = document.getElementById('tab-login');
  const signupTab  = document.getElementById('tab-signup');
  const indicator  = document.getElementById('tab-indicator');
  const successScr = document.getElementById('signup-success');
  if (!loginForm) return;

  // Tab switching
  function switchTab(active) {
    const isLogin = active === 'login';
    loginForm.classList.toggle('active', isLogin);
    signupForm.classList.toggle('active', !isLogin);
    loginTab.classList.toggle('active', isLogin);
    signupTab.classList.toggle('active', !isLogin);
    if (indicator) indicator.style.left = isLogin ? '0%' : '50%';
  }
  loginTab?.addEventListener('click', () => switchTab('login'));
  signupTab?.addEventListener('click', () => switchTab('signup'));
  switchTab('login');

  // Apply masks
  const phoneInput = signupForm?.querySelector('[name="telefone"]');
  const cpfInput   = signupForm?.querySelector('[name="cpf"]');
  if (phoneInput) maskPhone(phoneInput);
  if (cpfInput)   maskCPF(cpfInput);

  // Login submit
  loginForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = this.querySelector('.auth-error');
    const btn   = this.querySelector('[type="submit"]');
    const email = this.querySelector('[name="email"]').value.trim();
    const pass  = this.querySelector('[name="password"]').value;
    if (!email || !pass) { errEl.textContent = 'Preencha todos os campos.'; errEl.style.display='block'; return; }
    btn.textContent = 'Entrando...'; btn.disabled = true; errEl.style.display = 'none';
    if (window.APP_LOCAL_MODE) {
      try {
        const res = await fetch('/api/local-login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password: pass })
        });
        const data = await res.json();
        if (!res.ok || data?.error) {
          errEl.textContent = data?.error || 'E-mail ou senha incorretos.';
          errEl.style.display = 'block';
          btn.textContent = 'Entrar';
          btn.disabled = false;
          return;
        }
        storageSet('spark_local_user', JSON.stringify(data.user));
        window.location.href = '/';
        return;
      } catch (e2) {
        errEl.textContent = 'Erro ao fazer login local.';
        errEl.style.display = 'block';
        btn.textContent = 'Entrar';
        btn.disabled = false;
        return;
      }
    }
    const sb = getSB();
    const { error } = await sb.auth.signInWithPassword({ email, password: pass });
    if (error) { errEl.textContent = 'E-mail ou senha incorretos.'; errEl.style.display = 'block'; btn.textContent = 'Entrar'; btn.disabled = false; return; }
    window.location.href = '/';
  });

  // Signup submit
  signupForm?.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (window.APP_LOCAL_MODE) {
      const errEl = this.querySelector('.auth-error');
      errEl.textContent = 'Cadastro desabilitado no modo local.';
      errEl.style.display = 'block';
      return;
    }
    const errEl = this.querySelector('.auth-error');
    const btn   = this.querySelector('[type="submit"]');
    const nome     = this.querySelector('[name="nome"]').value.trim();
    const sobrenome= this.querySelector('[name="sobrenome"]').value.trim();
    const email    = this.querySelector('[name="email"]').value.trim();
    const telefone = this.querySelector('[name="telefone"]').value;
    const cpf      = this.querySelector('[name="cpf"]').value;
    const pass     = this.querySelector('[name="password"]').value;
    const pass2    = this.querySelector('[name="password2"]').value;

    if (!nome||!email||!pass) { errEl.textContent = 'Preencha todos os campos.'; errEl.style.display='block'; return; }
    if (pass !== pass2) { errEl.textContent = 'As senhas não coincidem.'; errEl.style.display='block'; return; }
    if (pass.length < 6) { errEl.textContent = 'Senha deve ter ao menos 6 caracteres.'; errEl.style.display='block'; return; }

    btn.textContent = 'Cadastrando...'; btn.disabled = true; errEl.style.display = 'none';
    const sb = getSB();
    const nomeCompleto = sobrenome ? nome + ' ' + sobrenome : nome;
    const { data, error } = await sb.auth.signUp({
      email,
      password: pass,
      options: {
        data: {
          nome:     nomeCompleto,
          cpf_cnpj: cpf.replace(/\D/g, '') || null,
          telefone: telefone.replace(/\D/g, '') || null,
        }
      }
    });
    if (error) { errEl.textContent = error.message; errEl.style.display = 'block'; btn.textContent = 'Cadastrar'; btn.disabled = false; return; }

    signupForm.style.display = 'none';
    if (successScr) { successScr.classList.add('active'); successScr.style.display = 'flex'; }
  });
}

// ─── FREIGHT CALCULATOR ───────────────────────────────────
function initFreightCalc() {
  const form    = document.getElementById('freight-form');
  if (!form) return;
  const cepInput = form.querySelector('#freight-cep');
  const calcBtn  = form.querySelector('#freight-calc-btn');
  const result   = document.getElementById('freight-result');

  cepInput?.addEventListener('input', function() {
    const val = this.value.replace(/\D/g,'');
    if (calcBtn) calcBtn.disabled = val.length < 8;
  });

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const cep = cepInput.value.replace(/\D/g,'');
    const qty = parseInt(document.getElementById('detail-qty')?.value || '1');
    if (cep.length < 8) return;
    if (result) result.innerHTML = '<div class="spinner spinner-sm" style="margin:1rem auto;"></div>';
    try {
      const codprod = parseInt(form.dataset.codprod || '0', 10) || 0;
      if (codprod <= 0) {
        if (result) result.innerHTML = '<p class="alert alert-error">Produto inválido para cálculo de frete.</p>';
        return;
      }
      const cepOrigem = String(window.APP_CEP_ORIGEM || '').replace(/\D/g, '');
      if (cepOrigem.length !== 8) { result.innerHTML = '<p class="alert alert-error">CEP de origem inválido.</p>'; return; }

      const unitPrice = parseFloat(form.dataset.price || '0') || 0;
      const payload = {
        cepOrigem,
        cepDestino: cep,
        peso:        parseFloat(form.dataset.peso        || '0.3') * qty,
        altura:      parseFloat(form.dataset.altura      || '10'),
        largura:     parseFloat(form.dataset.largura     || '15'),
        comprimento: parseFloat(form.dataset.comprimento || '20'),
      };
      if (unitPrice > 0) payload.valorDeclarado = unitPrice * qty;

      const res = await fetch('/api/frete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const opts = await res.json();
      if (opts?.error) throw new Error(String(opts.error));
      if (!Array.isArray(opts) || opts.length === 0) throw new Error('sem_servicos');

      result.innerHTML = opts.map(opt => `
        <div class="freight-option">
          <div>
            <div class="freight-option-name">${opt.nome}</div>
            ${opt.prazo ? `<div class="freight-option-days">Prazo: ${opt.prazo} dias úteis</div>` : ''}
          </div>
          <div class="freight-option-val">R$ ${opt.preco.toFixed(2).replace('.', ',')}</div>
        </div>`).join('');
    } catch(err) {
      if (result) result.innerHTML = '<p class="alert alert-error">Erro ao calcular frete.</p>';
    }
  });
}

// ─── ADD TO CART (product card / detail) ─────────────────
function addToCart(id, name, price, image, qty) {
  Cart.add({
    id: parseInt(id, 10),
    name,
    price: parseFloat(price),
    image: image || '/assets/images/produtos/logo.png',
    qty: parseInt(qty, 10) || 1
  });
}

window.addToCart = addToCart;
window.Cart = Cart;
window.CartSidebar = CartSidebar;

document.addEventListener('click', (e) => {
  const btn = e.target?.closest?.('[data-add-to-cart="1"]');
  if (!btn) return;
  if (btn.hasAttribute('disabled')) return;
  e.preventDefault();

  const id = btn.getAttribute('data-id');
  const name = btn.getAttribute('data-name') || '';
  const price = btn.getAttribute('data-price') || '0';
  const image = btn.getAttribute('data-image') || '/assets/images/produtos/logo.png';
  const peso  = btn.getAttribute('data-peso')  || '0.3';
  const qtyInputId = btn.getAttribute('data-qty-input');
  const qty = qtyInputId ? (document.getElementById(qtyInputId)?.value || '1') : (btn.getAttribute('data-qty') || '1');
  Cart.add({ id: parseInt(id, 10), name, price: parseFloat(price), image, qty: parseInt(qty, 10) || 1, peso: parseFloat(peso) });
});

// ─── LOAD MORE (product listing) ─────────────────────────

// Strict low-only validator for client-side rendered images:
//   1. Local site assets (/assets/...): allowed unconditionally.
//   2. Remote URLs: path must contain a 'low' whole-word AND no 'high' whole-word.
// The PHP backend enforces the same rules — this is defense in depth so a
// stale or hand-crafted JSON response can never produce a high-res render.
// Word boundaries in URL paths: / _ - . and start/end of path segment.
const HIGH_RE = /(^|[\/_.\-])high([\/_.\-]|$)/i;
const LOW_RE  = /(^|[\/_.\-])low([\/_.\-]|$)/i;
function safeImgUrl(url) {
  const FALLBACK = '/assets/images/produtos/logo.png';
  if (!url || typeof url !== 'string') return FALLBACK;
  try {
    const u = new URL(url, window.location.origin);
    if (u.origin === window.location.origin) return url;
    if (HIGH_RE.test(u.pathname)) return FALLBACK;
    if (/[?&](resolution|quality|res)[=_]high/i.test(u.search)) return FALLBACK;
    if (!LOW_RE.test(u.pathname)) return FALLBACK;
    return url;
  } catch (_) {
    return FALLBACK;
  }
}

function renderProductCard(p) {
  const img = safeImgUrl(p.image);
  const cartSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`;
  return `<div class="product-card">
  <a href="${escHtml(p.url)}" class="product-card__img">
    <img src="${escHtml(img)}" alt="${escHtml(p.name)}" width="400" height="400"
         loading="lazy" decoding="async"
         onerror="this.src='/assets/images/produtos/logo.png'">
  </a>
  <div class="product-card__body">
    <a href="${escHtml(p.url)}" class="product-card__name">${escHtml(p.name)}</a>
    <div class="product-card__price">${escHtml(p.price_fmt)}</div>
    <button class="product-card__btn" type="button" data-add-to-cart="1"
      data-id="${escHtml(p.id)}"
      data-name="${escHtml(p.name)}"
      data-price="${escHtml(p.price)}"
      data-image="${escHtml(img)}"
      data-qty="1">
      ${cartSvg} Adicionar
    </button>
  </div>
</div>`;
}

function initLoadMore() {
  const btn  = document.getElementById('load-more-btn');
  const wrap = document.getElementById('load-more-wrap');
  const grid = document.getElementById('products-grid');
  if (!btn || !grid) return;

  let loading         = false;
  let observer        = null;

  function teardown() {
    if (observer) { observer.disconnect(); observer = null; }
    wrap.remove();
  }

  async function loadNextPage() {
    if (loading) return;
    loading         = true;
    btn.disabled    = true;
    btn.textContent = 'Carregando…';

    const offset    = parseInt(btn.dataset.offset, 10) || 0;
    const categoria = btn.dataset.categoria || '0';
    const q         = btn.dataset.q || '';
    const params    = new URLSearchParams({ offset, categoria });
    if (q) params.set('q', q);

    try {
      const res  = await fetch('/api/produtos.php?' + params.toString());
      const data = await res.json();

      if (!data?.ok || !Array.isArray(data.products)) throw new Error('API error');

      data.products.forEach(p => {
        const tmp = document.createElement('div');
        tmp.innerHTML = renderProductCard(p);
        grid.appendChild(tmp.firstElementChild);
      });

      if (data.has_more) {
        btn.dataset.offset = data.next_offset;
        btn.disabled       = false;
        btn.textContent    = 'Ver mais produtos';
      } else {
        teardown();
      }
    } catch (err) {
      btn.disabled    = false;
      btn.textContent = 'Ver mais produtos';
      console.error('Erro ao carregar mais produtos:', err);
    } finally {
      loading = false;
    }
  }

  btn.addEventListener('click', loadNextPage);

  // Auto-trigger when the button scrolls into view (200 px before it becomes visible).
  // Falls back to click-only on browsers without IntersectionObserver support.
  if ('IntersectionObserver' in window) {
    observer = new IntersectionObserver(
      (entries) => { if (entries[0].isIntersecting && !loading && !btn.disabled) loadNextPage(); },
      { rootMargin: '200px 0px' }
    );
    observer.observe(btn);
  }
}

// ─── INIT ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // A11y: skip link target
  const main = document.querySelector('main.page-content');
  if (main && !main.id) {
    main.id = 'page-content';
    main.setAttribute('tabindex', '-1');
  }

  // A11y: desktop category dropdown (click + keyboard)
  const catDropdown = document.querySelector('.cat-dropdown');
  const catBtn = catDropdown?.querySelector('.cat-dropdown-btn');
  function setCatExpanded(v) {
    if (!catBtn) return;
    catBtn.setAttribute('aria-expanded', v ? 'true' : 'false');
    catDropdown?.classList.toggle('open', v);
  }
  catBtn?.addEventListener('click', () => {
    const isOpen = catDropdown?.classList.contains('open');
    setCatExpanded(!isOpen);
  });
  document.addEventListener('click', (e) => {
    if (!catDropdown) return;
    if (catDropdown.contains(e.target)) return;
    setCatExpanded(false);
  });

  // Init Supabase auth
  if (window.supabase) UserStore.init();

  // Cart & Search sidebar events
  document.getElementById('cart-open-btn')?.addEventListener('click',   () => CartSidebar.toggle());
  document.getElementById('cart-close-btn')?.addEventListener('click',  () => CartSidebar.close());
  document.getElementById('search-open-btn')?.addEventListener('click', () => SearchSidebar.toggle());
  document.getElementById('search-close-btn')?.addEventListener('click',() => SearchSidebar.close());
  document.getElementById('sidebar-overlay')?.addEventListener('click', () => { CartSidebar.close(); SearchSidebar.close(); });

  // Mobile bar
  document.getElementById('mobile-search-btn')?.addEventListener('click', () => SearchSidebar.toggle());
  document.getElementById('mobile-cart-btn')?.addEventListener('click',   () => CartSidebar.toggle());

  // Checkout link in cart
  document.getElementById('cart-checkout-link')?.addEventListener('click', () => { CartSidebar.close(); window.location.href = '/checkout.php'; });

  // Search form (sidebar)
  document.getElementById('search-submit')?.addEventListener('click', doSearch);
  document.getElementById('search-input')?.addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
  function doSearch() {
    const q = document.getElementById('search-input')?.value.trim();
    if (q) { SearchSidebar.close(); window.location.href = '/index.php?q=' + encodeURIComponent(q); }
  }

  // A11y: Esc fecha drawers e dropdown
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('cart-sidebar')?.classList.contains('open')) {
      CartSidebar.close();
      return;
    }
    if (document.getElementById('search-sidebar')?.classList.contains('open')) {
      SearchSidebar.close();
      return;
    }
    if (catDropdown?.classList.contains('open')) setCatExpanded(false);
  });

  // Init components
  initHeaderScroll();
  initBannerCarousel();
  initProductGallery();
  initQtySelector();
  initCepInputs();
  initFreightCalc();
  initLoginPage();
  initLoadMore();
  updateCartUI();
  renderCartItems();
});
