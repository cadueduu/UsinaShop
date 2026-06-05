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

// ─── CART STORE (memória + localStorage fallback) ─────────
// localStorage mirrors in-memory state on every change. Acts as a safety net
// for the 600ms DB debounce window: if the tab closes mid-flight, the next
// page load restores from localStorage before the DB sync runs.
const CART_LS_KEY = 'spark_cart_items';

function loadCartFromStorage() {
  try {
    const raw = localStorage.getItem(CART_LS_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return [];
  }
}

function saveCartToStorage(items) {
  try {
    localStorage.setItem(CART_LS_KEY, JSON.stringify(items));
  } catch (e) { /* quota or disabled — ignore */ }
}

function clearCartStorage() {
  try { localStorage.removeItem(CART_LS_KEY); } catch (e) { /* ignore */ }
}

const Cart = (() => {
  let items = loadCartFromStorage();

  function count()    { return items.reduce((s, i) => s + (parseInt(i.qty, 10) || 0), 0); }
  function total()    { return items.reduce((s, i) => s + (Number(i.price) * (parseInt(i.qty, 10) || 0)), 0); }
  function getAll()   { return [...items]; }
  function setItems(newItems) { items = newItems; saveCartToStorage(items); updateCartUI(); renderCartItems(); }

  function add(item) {
    const existing = items.find(i => i.id === item.id);
    const newQty = (parseInt(existing?.qty, 10) || 0) + (parseInt(item.qty, 10) || 1);
    const peso = parseFloat(item.peso || existing?.peso || 0.3);
    const altura      = parseInt(item.altura      || existing?.altura      || 10, 10);
    const largura     = parseInt(item.largura     || existing?.largura     || 15, 10);
    const comprimento = parseInt(item.comprimento || existing?.comprimento || 20, 10);
    if (existing) {
      existing.qty = newQty; existing.peso = peso;
      existing.altura = altura; existing.largura = largura; existing.comprimento = comprimento;
    } else {
      items.push({
        id: item.id, name: item.name, price: item.price, image: item.image,
        qty: parseInt(item.qty, 10) || 1, peso, altura, largura, comprimento,
      });
    }
    saveCartToStorage(items);
    updateCartUI();
    CartSidebar.open();
    if (!window.APP_LOCAL_MODE) upsertCartItemDB(item.id, newQty, peso);
  }

  function remove(id) {
    items = items.filter(i => i.id !== id);
    saveCartToStorage(items);
    updateCartUI();
    renderCartItems();
    if (!window.APP_LOCAL_MODE) upsertCartItemDB(id, 0, 0);
  }

  function setQty(id, qty) {
    const newQty = Math.max(1, qty);
    const item = items.find(i => i.id === id);
    if (item) { item.qty = newQty; }
    saveCartToStorage(items);
    updateCartUI();
    renderCartItems();
    if (!window.APP_LOCAL_MODE) upsertCartItemDB(id, newQty, item?.peso || 0.3);
  }

  function clear() {
    items = [];
    clearCartStorage();
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
        embPeso:     parseFloat(emb.peso || 0) || 0,
        embAltura:   parseInt(emb.altura       || prod.altura      || 10),
        embLargura:  parseInt(emb.largura      || prod.largura     || 15),
        embComprimento: parseInt(emb.comprimento || prod.comprimento || 20),
      };
    });

    Cart.setItems(cartItems);
    // DB is now canonical for this authenticated session.
    // setItems already saved the freshly-loaded items to localStorage above.
  } catch(e) { console.error('Erro ao carregar carrinho:', e); }
}

// Flush any pending debounced cart upserts before the tab unloads. Uses both
// 'pagehide' (modern, mobile-friendly) and 'beforeunload' (broad compat).
window.addEventListener('pagehide', flushAllPendingCartUpserts);
window.addEventListener('beforeunload', flushAllPendingCartUpserts);

const _cartUpsertTimers = {};
const _cartUpsertPending = {};
function upsertCartItemDB(id, qty, pesoUnitario = 0.3) {
  clearTimeout(_cartUpsertTimers[id]);
  _cartUpsertPending[id] = qty;
  _cartUpsertTimers[id] = setTimeout(() => {
    delete _cartUpsertPending[id];
    _flushCartUpsert(id, qty);
  }, 600);
}

// Flush every pending debounced upsert synchronously — called on beforeunload
// so changes made within the 600ms debounce window don't get lost when the
// user closes the tab.
async function flushAllPendingCartUpserts() {
  const tasks = [];
  for (const id of Object.keys(_cartUpsertPending)) {
    clearTimeout(_cartUpsertTimers[id]);
    const qty = _cartUpsertPending[id];
    delete _cartUpsertPending[id];
    tasks.push(_flushCartUpsert(id, qty));
  }
  if (tasks.length) await Promise.allSettled(tasks);
}

// Per-item AbortControllers: when a new flush starts, the previous in-flight
// request for the same codprod is cancelled so the latest desired qty always
// wins on the server, regardless of network reordering.
const _cartUpsertAborters = {};

async function _flushCartUpsert(id, qty) {
  const sb = getSB();
  if (!sb) return;

  if (_cartUpsertAborters[id]) {
    try { _cartUpsertAborters[id].abort(); } catch (_) { /* ignore */ }
  }
  const controller = new AbortController();
  _cartUpsertAborters[id] = controller;

  try {
    const { data: { session } } = await sb.auth.getSession();
    if (!session) return;
    if (qty <= 0) {
      await sb.from('carrinho')
        .delete()
        .eq('cliente_id', session.user.id)
        .eq('codprod', id)
        .abortSignal(controller.signal);
    } else {
      // peso_total é calculado automaticamente pelo trigger no banco (produto.peso × quantidade)
      await sb.from('carrinho')
        .upsert(
          { cliente_id: session.user.id, codprod: id, quantidade: qty },
          { onConflict: 'cliente_id,codprod' }
        )
        .abortSignal(controller.signal);
    }
  } catch(e) {
    if (e?.name !== 'AbortError') console.error('Erro ao sincronizar carrinho:', e);
  } finally {
    if (_cartUpsertAborters[id] === controller) delete _cartUpsertAborters[id];
  }
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
        <img src="${item.image}" alt="${escHtml(item.name)}" loading="lazy" decoding="async" onerror="this.src='/assets/images/produtos/logo.png'">
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
  } catch(e) { list.innerHTML = '<p class="text-sm text-gray-500">Não foi possível carregar as categorias. Tente recarregar a página.</p>'; }
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
    // Mirror Supabase access token to a cookie so server-side admin gating works.
    const syncTokenCookie = (tok) => {
      const secure = location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = 'sb_token=' + (tok || '') + '; Path=/; SameSite=Lax; Max-Age=' + (tok ? 3600 : 0) + secure;
    };
    if (session?.access_token) syncTokenCookie(session.access_token);

    sb.auth.onAuthStateChange(async (event, session) => {
      if (event === 'SIGNED_IN' && session?.user) {
        syncTokenCookie(session.access_token);
        await this.loadUser(session.user.id);
        await syncCartFromDB(session.user.id);
      } else if (event === 'TOKEN_REFRESHED' && session?.access_token) {
        syncTokenCookie(session.access_token);
      } else if (event === 'SIGNED_OUT') {
        syncTokenCookie('');
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
        btnAcct.querySelector('.header-action-label').textContent = '';
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

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 8000);

  try {
    const res  = await fetch(`https://viacep.com.br/ws/${clean}/json/`, { signal: controller.signal });
    const data = await res.json();

    if (data.erro) {
      showCepHint(cepInput, 'CEP não encontrado. Confira o número ou preencha o endereço manualmente.', true);
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
    if (e.name === 'AbortError') {
      showCepHint(cepInput, 'Não foi possível buscar o CEP. Preencha o endereço manualmente.', true);
    } else {
      showCepHint(cepInput, 'Erro ao consultar CEP. Preencha o endereço manualmente.', true);
    }
  } finally {
    clearTimeout(timeoutId);
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
  // Dismiss on focus of any sibling input (user moved on), or after 8s.
  const dismissTimer = setTimeout(() => hint.remove(), 8000);
  const dismissOnNext = () => { clearTimeout(dismissTimer); hint.remove(); };
  const form = input.closest('form, [data-address-form], .addr-edit-form, [id^="co-edit-"], [id^="edit-form-"]');
  form?.querySelectorAll('input, select, textarea').forEach(el => {
    if (el === input) return;
    el.addEventListener('focus', dismissOnNext, { once: true });
  });
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
    if (error) {
      const raw = String(error.message || '').toLowerCase();
      let friendly = 'Não foi possível concluir o cadastro. Verifique os dados e tente novamente.';
      if (raw.includes('already') || raw.includes('exists') || raw.includes('registered')) {
        friendly = 'Este e-mail já está cadastrado. Tente entrar ou use outro endereço.';
      } else if (raw.includes('password') || raw.includes('senha')) {
        friendly = 'Senha não atende aos requisitos. Use ao menos 6 caracteres.';
      } else if (raw.includes('email') || raw.includes('invalid')) {
        friendly = 'E-mail inválido. Confira o endereço informado.';
      }
      errEl.textContent = friendly;
      errEl.style.display = 'block';
      btn.textContent = 'Cadastrar';
      btn.disabled = false;
      return;
    }

    signupForm.style.display = 'none';
    if (successScr) { successScr.classList.add('active'); successScr.style.display = 'flex'; }
  });
}

// ─── FREIGHT CALCULATOR (cart-wide) ───────────────────────
const CART_CEP_LS_KEY = 'spark_cart_cep';

function initFreightCalc() {
  const form = document.getElementById('cart-freight-form');
  if (!form || form.dataset.initialized === '1') return;
  form.dataset.initialized = '1';

  const cepInput = form.querySelector('#cart-freight-cep');
  const calcBtn  = form.querySelector('#cart-freight-calc-btn');
  const result   = document.getElementById('cart-freight-result');

  try {
    const saved = localStorage.getItem(CART_CEP_LS_KEY);
    if (saved && cepInput) { cepInput.value = saved; if (calcBtn) calcBtn.disabled = saved.replace(/\D/g,'').length < 8; }
  } catch (_) { /* ignore */ }

  cepInput?.addEventListener('input', function() {
    const val = this.value.replace(/\D/g,'');
    if (calcBtn) calcBtn.disabled = val.length < 8;
  });

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const cep = cepInput.value.replace(/\D/g,'');
    if (cep.length < 8) return;

    const items = Cart.getAll();
    if (items.length === 0) {
      result.innerHTML = '<p class="alert alert-error">Adicione itens ao carrinho para calcular o frete.</p>';
      return;
    }

    try { localStorage.setItem(CART_CEP_LS_KEY, cep); } catch (_) { /* ignore */ }

    if (result) result.innerHTML = '<div class="spinner spinner-sm" style="margin:1rem auto;"></div>';

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);

    try {
      const cepOrigem = String(window.APP_CEP_ORIGEM || '').replace(/\D/g, '');
      if (cepOrigem.length !== 8) {
        result.innerHTML = '<p class="alert alert-error">CEP de origem inválido. Recarregue a página e tente novamente.</p>';
        return;
      }

      const cartItemsFull = (typeof Cart !== 'undefined') ? Cart.getAll() : items;
      const ids = Array.from(new Set(cartItemsFull.map(it => parseInt(it.id, 10)).filter(v => Number.isFinite(v) && v > 0)));
      const sb = getSB();
      const packMap = {};
      if (sb && ids.length > 0) {
        const { data } = await sb
          .from('produto')
          .select('codprod,codprodemb,embalagem:codprodemb(peso,altura,largura,comprimento)')
          .in('codprod', ids);
        if (Array.isArray(data)) {
          data.forEach(r => {
            const emb = r?.embalagem || null;
            if (!r?.codprod || !emb) return;
            packMap[String(r.codprod)] = emb;
          });
        }
      }

      const missing = [];
      let pesoGramas = 0;
      let altura = 0, largura = 0, comprimento = 0;
      let subtotal = 0;
      cartItemsFull.forEach(it => {
        const qty = parseInt(it.qty, 10) || 0;
        const emb = packMap[String(it.id)] || null;
        if (!emb) { missing.push(it.id); return; }
        const pPeso = parseFloat(emb.peso || 0);
        const pA = parseFloat(emb.altura || 0);
        const pL = parseFloat(emb.largura || 0);
        const pC = parseFloat(emb.comprimento || 0);
        if (!(pPeso > 0 && pA > 0 && pL > 0 && pC > 0)) { missing.push(it.id); return; }
        pesoGramas += Math.max(1, Math.round(pPeso * qty * 1000));
        altura = Math.max(altura, Math.ceil(pA));
        largura = Math.max(largura, Math.ceil(pL));
        comprimento = Math.max(comprimento, Math.ceil(pC));
        subtotal += (Number(it.price) || 0) * qty;
      });

      if (missing.length) {
        result.innerHTML = '<p class="alert alert-error">Não foi possível calcular o frete: há produto(s) sem embalagem configurada.</p>';
        return;
      }

      pesoGramas = Math.max(300, pesoGramas);
      altura = Math.max(1, altura || 10);
      largura = Math.max(1, largura || 15);
      comprimento = Math.max(1, comprimento || 20);

      function parsePrecoItem(item) {
        if (!item || typeof item !== 'object') return null;
        const raw = item.pcTotal ?? item.pcFinal ?? item.pcServico ?? item.pcBase ?? null;
        if (raw == null) return null;
        let s = String(raw).replace('R$', '').trim();
        if (s.includes(',') && s.includes('.')) {
          s = s.replace(/\./g, '').replace(',', '.');
        } else if (s.includes(',')) {
          s = s.replace(',', '.');
        }
        const n = parseFloat(s);
        return isFinite(n) ? n : null;
      }

      async function fetchCorreiosPreco(code) {
        const res = await fetch('/api/correios-preco.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ coProduto: code, cepOrigem, cepDestino: cep, pesoGramas, altura, largura, comprimento, valorDeclarado: subtotal }),
          signal: controller.signal,
        });
        const data = await res.json();
        if (data?.error) throw new Error(String(data.error));
        const valor = parsePrecoItem(data?.item);
        if (!valor || valor <= 0) throw new Error('preco_invalido');
        return { valor, nome: String(data?.item?.noProduto || data?.item?.coProduto || code) };
      }

      async function fetchCorreiosPrazo(code) {
        const res = await fetch('/api/correios-data-entrega.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ coProduto: code, cepOrigem, cepDestino: cep }),
          signal: controller.signal,
        });
        const data = await res.json();
        if (data?.error) throw new Error(String(data.error));
        const prazo = data?.prazoEntrega ?? data?.prazoItem?.prazoEntrega ?? null;
        const n = prazo != null ? parseInt(prazo, 10) : null;
        return (n && isFinite(n) && n > 0) ? n : null;
      }

      const servicos = [
        { code: '03298', label: 'PAC' },
        { code: '03220', label: 'SEDEX' },
      ];

      const settled = await Promise.allSettled(servicos.map(async (s) => {
        const preco = await fetchCorreiosPreco(s.code);
        let prazo = null;
    try { prazo = await fetchCorreiosPrazo(s.code); } catch (_) { prazo = null; }
        return { code: s.code, label: s.label, preco: preco.valor, prazo };
      }));

      const opts = [];
      settled.forEach((s, idx) => {
        const svc = servicos[idx];
        if (s.status === 'fulfilled' && s.value?.preco && s.value.preco > 0) {
          opts.push({ nome: svc.label, preco: s.value.preco, prazo: s.value.prazo });
        }
      });

      if (opts.length === 0) throw new Error('sem_servicos');

      result.innerHTML = opts.map(opt => `
        <div class="freight-option">
          <div>
            <div class="freight-option-name">${opt.nome}</div>
            <div class="freight-option-days">${opt.prazo
              ? `Prazo: ${opt.prazo} dias úteis`
              : 'Prazo de entrega indisponível no momento'}</div>
          </div>
          <div class="freight-option-val">R$ ${opt.preco.toFixed(2).replace('.', ',')}</div>
        </div>`).join('');
    } catch(err) {
      if (!result) return;
      if (err?.name === 'AbortError') {
        result.innerHTML = '<p class="alert alert-error">A consulta de frete demorou demais. Verifique sua conexão e tente novamente.</p>';
      } else {
        result.innerHTML = '<p class="alert alert-error">Não foi possível calcular o frete agora. Tente novamente em instantes.</p>';
      }
    } finally {
      clearTimeout(timeoutId);
    }
  });
}

// ─── ADD TO CART (product card / detail) ─────────────────
function addToCart(id, name, price, image, qty, peso, altura, largura, comprimento) {
  Cart.add({
    id: parseInt(id, 10),
    name,
    price: parseFloat(price),
    image: image || '/assets/images/produtos/logo.png',
    qty: parseInt(qty, 10) || 1,
    peso: peso != null ? parseFloat(peso) : undefined,
    altura: altura != null ? parseInt(altura, 10) : undefined,
    largura: largura != null ? parseInt(largura, 10) : undefined,
    comprimento: comprimento != null ? parseInt(comprimento, 10) : undefined,
  });
}

window.addToCart = addToCart;
window.Cart = Cart;
window.CartSidebar = CartSidebar;
window.flushAllPendingCartUpserts = flushAllPendingCartUpserts;

// Opportunistic stock check: queries Supabase directly at click time so a
// product that went out of stock between page load and click can't slip in.
// Fails open — if Supabase is unreachable or LOCAL_DATA_MODE is set, the add
// proceeds (the legacy behavior). Returns the requested qty if allowed, or a
// reduced qty / 0 when there isn't enough stock.
async function checkStockAvailable(codprod, requestedQty) {
  if (window.APP_LOCAL_MODE) return requestedQty;
  const sb = getSB();
  if (!sb) return requestedQty;
  try {
    const { data, error } = await sb
      .from('estoque')
      .select('estoque_disponivel')
      .eq('codprod', codprod)
      .limit(1)
      .maybeSingle();
    if (error || !data) return requestedQty;
    const available = parseInt(data.estoque_disponivel, 10) || 0;
    return Math.min(requestedQty, available);
  } catch (e) {
    return requestedQty;
  }
}

document.addEventListener('click', async (e) => {
  const btn = e.target?.closest?.('[data-add-to-cart="1"]');
  if (!btn) return;
  if (btn.hasAttribute('disabled')) return;
  e.preventDefault();

  const id = parseInt(btn.getAttribute('data-id'), 10);
  const name = btn.getAttribute('data-name') || '';
  const price = btn.getAttribute('data-price') || '0';
  const image = btn.getAttribute('data-image') || '/assets/images/produtos/logo.png';
  const peso  = btn.getAttribute('data-peso')  || '0.3';
  const altura      = btn.getAttribute('data-altura')      || '10';
  const largura     = btn.getAttribute('data-largura')     || '15';
  const comprimento = btn.getAttribute('data-comprimento') || '20';
  const qtyInputId = btn.getAttribute('data-qty-input');
  const requestedQty = parseInt(qtyInputId ? (document.getElementById(qtyInputId)?.value || '1') : (btn.getAttribute('data-qty') || '1'), 10) || 1;

  const allowedQty = await checkStockAvailable(id, requestedQty);
  if (allowedQty <= 0) {
    alert('Este produto está sem estoque no momento.');
    return;
  }
  if (allowedQty < requestedQty) {
    alert(`Apenas ${allowedQty} unidade(s) disponível(is) em estoque. Quantidade ajustada.`);
  }
  Cart.add({
    id, name, price: parseFloat(price), image, qty: allowedQty,
    peso: parseFloat(peso),
    altura: parseInt(altura, 10), largura: parseInt(largura, 10), comprimento: parseInt(comprimento, 10),
  });
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
    if (/[?&](resolution|quality|res)[=_]high/i.test(u.search)) return FALLBACK;
    if (LOW_RE.test(u.pathname)) return url;
    if (u.pathname.includes('/storage/v1/render/image/public/')) {
      const w = parseInt(u.searchParams.get('width') || '', 10);
      const q = parseInt(u.searchParams.get('quality') || '', 10);
      if (Number.isFinite(w) && Number.isFinite(q) && w > 0 && w <= 900 && q > 0 && q <= 80) return url;
    }
    if (HIGH_RE.test(u.pathname)) return FALLBACK;
    return FALLBACK;
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
         fetchpriority="low" loading="lazy" decoding="async"
         onerror="this.closest('.product-card')?.remove()">
  </a>
  <div class="product-card__body">
    <a href="${escHtml(p.url)}" class="product-card__name">${escHtml(p.name)}</a>
    <div class="product-card__price">${escHtml(p.price_fmt)}</div>
    <button class="product-card__btn" type="button" data-add-to-cart="1"
      data-id="${escHtml(p.id)}"
      data-name="${escHtml(p.name)}"
      data-price="${escHtml(p.price)}"
      data-image="${escHtml(img)}"
      data-peso="${escHtml(p.peso ?? 0.3)}"
      data-altura="${escHtml(p.altura ?? 10)}"
      data-largura="${escHtml(p.largura ?? 15)}"
      data-comprimento="${escHtml(p.comprimento ?? 20)}"
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

  // UX: mostra CHUNK_SIZE cards por vez na tela; a API entrega lotes maiores e
  // o excedente fica num buffer em memória. Quando o buffer fica curto, dispara
  // prefetch da próxima página em background — o usuário nunca espera no scroll.
  const CHUNK_SIZE       = 8;
  const PREFETCH_AT      = 8; // quando buffer.length <= PREFETCH_AT, prefetch
  const categoria        = btn.dataset.categoria || '0';
  const q                = btn.dataset.q || '';
  const subtitle         = document.getElementById('products-subtitle');

  let buffer        = [];           // produtos baixados e ainda não renderizados
  let nextOffset    = parseInt(btn.dataset.offset, 10) || 0;
  let serverHasMore = true;         // o backend ainda tem páginas?
  let fetching      = null;         // Promise da requisição em voo (ou null)
  let isInitial     = btn.dataset.initial === '1';
  let totalShown    = 0;
  let observer      = null;

  function teardown() {
    if (observer) { observer.disconnect(); observer = null; }
    wrap.remove();
  }

  function clearSkeleton() {
    grid.querySelectorAll('.product-card--skeleton').forEach(el => el.remove());
    grid.removeAttribute('aria-busy');
  }

  function updateSubtitle() {
    if (!subtitle) return;
    if (totalShown === 0 && !serverHasMore && buffer.length === 0) {
      subtitle.textContent = 'Nenhum produto encontrado';
    } else if (serverHasMore || buffer.length > 0) {
      subtitle.textContent = `Exibindo ${totalShown} produtos`;
    } else {
      subtitle.textContent = `${totalShown} produto${totalShown !== 1 ? 's' : ''} encontrado${totalShown !== 1 ? 's' : ''}`;
    }
  }

  // Faz UMA requisição à API e devolve os produtos. Não renderiza.
  async function fetchNextPage() {
    if (!serverHasMore || fetching) return fetching || Promise.resolve([]);
    const params = new URLSearchParams({ offset: nextOffset, categoria });
    if (q) params.set('q', q);

    fetching = (async () => {
      try {
        const res  = await fetch('/api/produtos.php?' + params.toString());
        const data = await res.json();
        if (!data?.ok || !Array.isArray(data.products)) throw new Error('API error');
        nextOffset    = data.next_offset;
        serverHasMore = !!data.has_more;
        return data.products;
      } catch (err) {
        console.error('Erro ao carregar produtos:', err);
        return [];
      } finally {
        fetching = null;
      }
    })();

    return fetching;
  }

  // Renderiza até CHUNK_SIZE produtos do buffer no grid.
  function renderChunkFromBuffer() {
    const chunk = buffer.splice(0, CHUNK_SIZE);
    if (chunk.length === 0) return 0;
    const frag = document.createDocumentFragment();
    chunk.forEach(p => {
      const tmp = document.createElement('div');
      tmp.innerHTML = renderProductCard(p);
      if (tmp.firstElementChild) frag.appendChild(tmp.firstElementChild);
    });
    grid.appendChild(frag);
    totalShown += chunk.length;
    return chunk.length;
  }

  // Garante que o buffer tem ao menos `min` produtos (busca quando necessário).
  async function ensureBuffer(min) {
    while (buffer.length < min && serverHasMore) {
      const products = await fetchNextPage();
      if (products.length === 0) break;
      buffer = buffer.concat(products);
    }
  }

  // Dispara prefetch em background sem bloquear (não awaita).
  function maybePrefetch() {
    if (buffer.length <= PREFETCH_AT && serverHasMore && !fetching) {
      fetchNextPage().then(products => {
        if (products.length) buffer = buffer.concat(products);
      });
    }
  }

  let loading = false;

  // Mostra mais um chunk (do buffer, ou buscando se preciso).
  async function showNextChunk() {
    if (loading) return;
    loading = true;
    btn.disabled = true;
    if (!isInitial) btn.textContent = 'Carregando…';

    try {
      if (buffer.length < CHUNK_SIZE) {
        await ensureBuffer(CHUNK_SIZE);
      }

      if (isInitial) clearSkeleton();

      const rendered = renderChunkFromBuffer();
      isInitial = false;
      btn.dataset.initial = '0';

      updateSubtitle();

      const stillHasContent = buffer.length > 0 || serverHasMore;
      if (!stillHasContent && rendered === 0 && totalShown === 0) {
        // nenhum produto disponível
        teardown();
        return;
      }
      if (!stillHasContent) {
        teardown();
        return;
      }

      btn.disabled = false;
      btn.style.visibility = '';
      btn.textContent = 'Ver mais produtos';

      // Prefetch da próxima página enquanto o usuário olha a atual.
      maybePrefetch();
    } catch (err) {
      if (isInitial) {
        clearSkeleton();
        if (subtitle) subtitle.textContent = 'Erro ao carregar produtos. Tente recarregar a página.';
      }
      btn.disabled = false;
      btn.style.visibility = '';
      btn.textContent = 'Ver mais produtos';
      console.error('Erro ao mostrar produtos:', err);
    } finally {
      loading = false;
    }
  }

  btn.addEventListener('click', showNextChunk);

  // Primeira carga: começa imediatamente.
  showNextChunk();

  // Auto-trigger ao rolar até perto do botão.
  if ('IntersectionObserver' in window) {
    observer = new IntersectionObserver(
      (entries) => { if (entries[0].isIntersecting && !loading && !btn.disabled) showNextChunk(); },
      { rootMargin: '400px 0px' }
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
  document.getElementById('cart-checkout-link')?.addEventListener('click', async () => {
    CartSidebar.close();
    try { if (typeof flushAllPendingCartUpserts === 'function') await flushAllPendingCartUpserts(); } catch (_) {}
    window.location.href = '/checkout.php';
  });

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
