<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/featured.php';

// Search query
$search = trim($_GET['q'] ?? '');
$is_search = $search !== '';

$page_title = $is_search ? 'Busca: ' . $search : 'Início';
$page_desc  = 'Spark Eletrônica — Fontes, carregadores e soluções em eletrônica';

// Fetch categories (used by header and search sidebar)
$categories = fetch_categories(false);

// Fetch products
if ($is_search) {
    // Search by name — fire both field queries in parallel via sb_multi, then merge.
    $base_select  = 'codprod,descrprod,comnome';
    $search_batch = sb_multi([
        'by_descr'  => ['produto', ['select' => $base_select, 'descrprod' => 'ilike.*' . $search . '*', 'limit' => 40]],
        'by_comnome'=> ['produto', ['select' => $base_select, 'comnome'   => 'ilike.*' . $search . '*', 'limit' => 40]],
    ]);
    $merged = [];
    foreach (array_merge($search_batch['by_descr'] ?? [], $search_batch['by_comnome'] ?? []) as $p) {
        $merged[$p['codprod']] = $p;
    }
    // listing_mode=true: skips specs, filters unavailable before image fetch, returns filtered list.
    $search_results = enrich_products(array_values($merged), true);
} else {
    $destaques     = fetch_highlights(8);
    $mais_vendidos = fetch_best_sellers(8);
}

// Banner slides configuration. `image` is the basename without extension —
// markup serves .webp first and falls back to .jpg via <picture>.
$banner_slides = [
    [
        'badge'    => '🔋 Alta Performance',
        'title'    => 'Fontes e Carregadores Industriais',
        'subtitle' => 'Confiabilidade e eficiência para sua operação.',
        'cta_text' => 'Ver Produtos',
        'cta_url'  => '/products.php',
        'image'    => '/assets/images/banners/banner1',
        'bg_color' => '#111827',
    ],
    [
        'badge'    => '⚡ Estoque Pronto',
        'title'    => 'Eletrônica para Indústria',
        'subtitle' => 'Mais de 1.000 itens disponíveis para pronta entrega.',
        'cta_text' => 'Explorar Catálogo',
        'cta_url'  => '/products.php',
        'image'    => '/assets/images/banners/banner2',
        'bg_color' => '#1e3a5f',
    ],
    [
        'badge'    => '✅ Qualidade Garantida',
        'title'    => 'Potência que Transforma',
        'subtitle' => 'Soluções para todos os segmentos do mercado.',
        'cta_text' => 'Saiba Mais',
        'cta_url'  => '/products.php',
        'image'    => '/assets/images/banners/banner3',
        'bg_color' => '#1a1a2e',
    ],
];

// Preload the LCP banner. Only on viewports that actually render the carousel
// (carousel is display:none below 640px). imagesrcset hands the browser the
// modern WebP candidate for preloading.
$preload_image      = $banner_slides[0]['image'] . '.webp';
$preload_image_jpg  = $banner_slides[0]['image'] . '.jpg';
$preload_image_media = '(min-width: 640px)';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>

<main class="page-content">

<?php if ($is_search): ?>
<!-- ─── SEARCH RESULTS ──────────────────────────────── -->
<section class="container" style="padding:2.5rem 1rem;">
  <div class="section-header">
    <h1 class="section-title">Resultados para: "<?= htmlspecialchars($search) ?>"</h1>
  </div>
  <?php if (empty($search_results)): ?>
    <div class="no-products">
      <p>Nenhum produto encontrado para "<strong><?= htmlspecialchars($search) ?></strong>".</p>
      <a href="/products.php">Ver todos os produtos →</a>
    </div>
  <?php else: ?>
    <div class="products-grid">
      <?php foreach ($search_results as $p): ?>
        <?php include __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php else: ?>
<!-- ─── BANNER HERO ──────────────────────────────────── -->
<!-- Desktop -->
<div class="banner-carousel" style="display:none;" id="desktop-banner">
  <!-- Progress bar -->
  <div class="banner-progress"><div class="banner-progress-bar"></div></div>
  <?php foreach ($banner_slides as $i => $slide): ?>
  <div class="banner-slide<?= $i===0?' active':'' ?>" style="background-color:<?= $slide['bg_color'] ?>;">
    <?php if ($i === 0): /* LCP image: eager + fetchpriority=high */ ?>
    <picture>
      <source type="image/webp" srcset="<?= htmlspecialchars($slide['image']) ?>.webp">
      <img src="<?= htmlspecialchars($slide['image']) ?>.jpg"
           alt="" width="2400" height="543"
           fetchpriority="high" decoding="async">
    </picture>
    <?php else: /* Off-screen slides: deferred. JS swaps data-src->src on first activation. */ ?>
    <picture>
      <source data-srcset="<?= htmlspecialchars($slide['image']) ?>.webp" type="image/webp">
      <img alt="" width="2400" height="543" decoding="async"
           data-src="<?= htmlspecialchars($slide['image']) ?>.jpg">
    </picture>
    <?php endif; ?>
    <div class="banner-overlay"></div>
    <div class="banner-content">
      <span class="banner-badge"><?= $slide['badge'] ?></span>
      <h2 class="banner-title"><?= htmlspecialchars($slide['title']) ?></h2>
      <p  class="banner-sub"><?= htmlspecialchars($slide['subtitle']) ?></p>
      <a href="<?= $slide['cta_url'] ?>" class="banner-cta"><?= $slide['cta_text'] ?> →</a>
    </div>
  </div>
  <?php endforeach; ?>
  <!-- Arrows -->
  <button class="banner-arrow banner-arrow-prev" aria-label="Anterior">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
  </button>
  <button class="banner-arrow banner-arrow-next" aria-label="Próximo">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
  </button>
  <!-- Dots -->
  <div class="banner-dots">
    <?php for($i=0;$i<count($banner_slides);$i++): ?>
      <button class="banner-dot<?= $i===0?' active':'' ?>" aria-label="Slide <?= $i+1 ?>"></button>
    <?php endfor; ?>
  </div>
</div>
<script>document.getElementById('desktop-banner').style.display='';</script>

<!-- Mobile Hero -->
<div class="mobile-hero">
  <img src="/assets/images/produtos/logo.png" alt="Spark Eletrônica"
       width="423" height="251" style="width:10rem;height:auto;"
       decoding="async" onerror="this.style.display='none'">
  <h1 class="mobile-hero-title">Potência que <span>Transforma</span></h1>
  <p style="color:rgba(255,255,255,.7);font-size:.875rem;">Eletrônica industrial para você</p>
  <a href="/products.php" class="mobile-hero-cta">Ver Produtos →</a>
</div>

<!-- ─── DESTAQUES ────────────────────────────────────── -->
<section class="section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Destaques</h2>
      <a href="/products.php" class="section-link">Ver todos →</a>
    </div>
    <div class="products-grid">
      <?php foreach (array_slice(array_values($destaques), 0, 8) as $p): ?>
        <?php include __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── CTA STRIP ────────────────────────────────────── -->
<div class="cta-strip">
  <div class="container">
    <h2 class="cta-strip-title">Potência que <span class="accent">Transforma</span></h2>
    <p class="cta-strip-sub">
      Soluções em eletrônica industrial com qualidade comprovada, suporte técnico e pronta entrega para todo o Brasil.
    </p>
  </div>
</div>

<!-- ─── MAIS VENDIDOS ────────────────────────────────── -->
<section class="section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Mais Vendidos</h2>
      <a href="/products.php" class="section-link">Ver todos →</a>
    </div>
    <div class="products-grid">
      <?php foreach (array_slice(array_values($mais_vendidos), 0, 8) as $p): ?>
        <?php include __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
