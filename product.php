<?php
require_once __DIR__ . '/includes/config.php';

$pid = intval($_GET['id'] ?? 0);
if (!$pid) { header('Location: /products.php'); exit; }

// Fetch product with all related data
$result = sb('produto', [
    'select'  => 'codprod,descrprod,comnome,desccurta,descrprodoed,codgrupoprod,peso,altura,largura,comprimento',
    'codprod' => 'eq.' . $pid,
    'limit'   => 1,
]);
if (!empty($result)) {
    $result = enrich_products($result);
}

if (empty($result)) {
    http_response_code(404);
    $page_title = 'Produto não encontrado';
    include __DIR__ . '/includes/head.php';
    echo '<div style="text-align:center;padding:4rem 1rem;"><h1>Produto não encontrado.</h1><a href="/products.php">Ver produtos</a></div>';
    include __DIR__ . '/includes/footer.php';
    echo '<script defer src="/assets/js/main.js"></script></body></html>';
    exit;
}

$p      = $result[0];
$name   = prod_name($p);
$price  = $p['preco'][0]['vlr_venda']           ?? 0;
$stock  = $p['estoque'][0]['estoque_disponivel'] ?? 0;

// Restrict the gallery to verified low-resolution variants. Any image whose
// path/URL is not a confirmed low asset is dropped — the storefront must never
// render or rotate to a high-res variant.
$images = array_values(array_filter(
    $p['produto_imagem'] ?? [],
    function ($img) {
        if (!empty($img['path'])) {
            $p = (string)$img['path'];
            if (preg_match('/(^|[\/_.\-])high([\/_.\-]|$)/i', $p)) return false;
            return (bool) preg_match('/(^|[\/_.\-])low([\/_.\-]|$)/i', $p);
        }
        return !empty($img['url']) && is_low_resolution_image_url((string)$img['url']);
    }
));
if (!empty($images)) usort($images, fn($a,$b) => ($a['ordem']??999) - ($b['ordem']??999));
// Main hero image: larger width + slightly higher quality than the catalog cards.
$main_img = !empty($images)
          ? product_image_render_url($images[0], 800, 75, 'webp')
          : '/assets/images/produtos/logo.png';
$main_is_low = is_low_resolution_image_url($main_img);
$specs    = $p['especificacao'] ?? [];
$desc     = $p['descrprodoed'] ?? $p['desccurta'] ?? '';
$peso     = floatval($p['peso']       ?? 0.3);  // kg
$altura   = intval($p['altura']       ?? 10);   // cm
$largura  = intval($p['largura']      ?? 15);
$comprimento = intval($p['comprimento'] ?? 20);

// Fetch category breadcrumb
$categories  = fetch_categories(false);
$cat_id      = $p['codgrupoprod'] ?? 0;
$breadcrumb_group = null;
$breadcrumb_sub   = null;
foreach ($categories as $gid => $g) {
    if ($gid === $cat_id) { $breadcrumb_group = $g; break; }
    if (isset($g['children'][$cat_id])) {
        $breadcrumb_group = $g;
        $breadcrumb_sub   = $g['children'][$cat_id];
        break;
    }
}

$page_title = $name;
$page_desc  = $p['desccurta'] ?? $name;

// Preload the gallery LCP image so the browser fetches it during HTML parse
// instead of waiting until the <img> tag is encountered. $main_img is already
// the proxied URL — head.php emits href-only preload when no _jpg pair is set.
$preload_image = $main_img;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>

<main class="page-content">
<div class="container" style="padding:1.5rem 1rem 4rem;">

  <!-- Breadcrumb (desktop only) -->
  <nav class="breadcrumb">
    <a href="/index.php">Home</a>
    <span class="breadcrumb-sep">/</span>
    <a href="/products.php">Produtos</a>
    <?php if ($breadcrumb_group): ?>
      <span class="breadcrumb-sep">/</span>
      <a href="/products.php?categoria=<?= array_search($breadcrumb_group, $categories) ?>"><?= htmlspecialchars(cat_name($breadcrumb_group['descr_grupo'])) ?></a>
    <?php endif; ?>
    <?php if ($breadcrumb_sub): ?>
      <span class="breadcrumb-sep">/</span>
      <a href="/products.php?categoria=<?= $cat_id ?>"><?= htmlspecialchars(cat_name($breadcrumb_sub['descr_grupo'])) ?></a>
    <?php endif; ?>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current"><?= htmlspecialchars($name) ?></span>
  </nav>

  <!-- Product detail card -->
  <div class="product-detail-wrap">
    <div class="detail-grid">

      <!-- ── Left: Gallery ── -->
      <div>
        <!-- Main image -->
        <div class="gallery-main">
          <img id="gallery-main-img"
               src="<?= htmlspecialchars($main_img) ?>"
               alt="<?= htmlspecialchars($name) ?>"
               width="800" height="800"
               <?php if ($main_is_low): ?>fetchpriority="high"<?php endif; ?>
               decoding="async"
               onerror="this.src='/assets/images/produtos/logo.png'"
               style="transition:opacity .2s;">
          <?php if (count($images) > 1): ?>
          <button class="gallery-arrow gallery-arrow-prev" type="button" aria-label="Imagem anterior">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <button class="gallery-arrow gallery-arrow-next" type="button" aria-label="Próxima imagem">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
          </button>
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
        <div class="gallery-dots" id="gallery-dots">
          <?php foreach ($images as $i => $img): ?>
          <button class="gallery-dot<?= $i===0?' active':'' ?>" type="button" data-gallery-dot="<?= $i ?>" aria-label="Ir para imagem <?= $i + 1 ?>"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- Thumbnails -->
        <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs" style="margin-top:.75rem;">
          <?php foreach ($images as $i => $img): ?>
          <?php
            // Thumbs are 120×120 in the layout; ask Supabase for ~200px so retina
            // displays still get a crisp render without re-fetching the hero.
            $thumb_url = product_image_render_url($img, 200, 60, 'webp');
            // Gallery swap target — same render as the hero (800×75) so rotation
            // does not stretch the small thumb when it takes over the main slot.
            $gallery_url = product_image_render_url($img, 800, 75, 'webp');
            /* Eager-load the first 3 thumbs: they sit directly under the main image
               (above the fold on desktop) and feed the auto-rotating gallery, so
               deferring them just produces visible flicker on rotation. */
            $thumb_loading = $i < 3 ? 'eager' : 'lazy';
            /* fetchpriority is set strictly: 'high' only on the first thumb AND
               only when it's a confirmed low asset (matches the preloaded main
               image); other thumbs are explicitly low so the browser doesn't
               compete with the main image. Any non-low URL (defense in depth —
               $images is already filtered to low-only) gets no fetchpriority. */
            $thumb_is_low  = is_low_resolution_image_url($thumb_url);
            $thumb_fetchpri = $thumb_is_low ? ($i === 0 ? 'high' : 'low') : '';
          ?>
          <div class="gallery-thumb<?= $i===0?' active':'' ?>"
               data-gallery-src="<?= htmlspecialchars($gallery_url) ?>">
            <img src="<?= htmlspecialchars($thumb_url) ?>"
                 alt="<?= htmlspecialchars($name) ?> - foto <?= $i+1 ?>"
                 width="120" height="120"
                 loading="<?= $thumb_loading ?>"
                 <?php if ($thumb_fetchpri !== ''): ?>fetchpriority="<?= $thumb_fetchpri ?>"<?php endif; ?>
                 decoding="async"
                 onerror="this.src='/assets/images/produtos/logo.png'">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── Right: Info & Actions ── -->
      <div>
        <!-- Title -->
        <h1 class="detail-title"><?= htmlspecialchars($name) ?></h1>

        <!-- Price -->
        <div class="detail-price">
          <span><?= fmt_brl($price) ?></span>
          <span class="detail-price-note">à vista ou em 12x no cartão</span>
        </div>

        <!-- Stock -->
        <div class="detail-stock <?= $stock > 0 ? 'available' : 'unavailable' ?>">
          <?= $stock > 0
              ? '✓ Em estoque (' . intval($stock) . ' un.)'
              : '✗ Fora de estoque' ?>
        </div>

        <!-- Quantity selector -->
        <div style="margin-bottom:1rem;">
          <div class="qty-selector">
            <button class="qty-btn" data-minus aria-label="Diminuir">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"/></svg>
            </button>
            <input type="number" class="qty-val" id="detail-qty" value="1" min="1" readonly>
            <button class="qty-btn" data-plus aria-label="Aumentar">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14"/></svg>
            </button>
          </div>
        </div>

        <!-- Buy button -->
        <button
          id="buy-btn"
          class="buy-btn"
          <?= $stock <= 0 ? 'disabled' : '' ?>
          type="button"
          data-add-to-cart="1"
          data-id="<?= $pid ?>"
          data-name="<?= htmlspecialchars($name) ?>"
          data-price="<?= htmlspecialchars((string)$price) ?>"
          data-image="<?= htmlspecialchars($main_img) ?>"
          data-peso="<?= $peso ?>"
          data-qty-input="detail-qty"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          <?= $stock > 0 ? 'Comprar Agora' : 'Fora de Estoque' ?>
        </button>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:1.25rem 0;">

        <!-- Freight calculator -->
        <div class="freight-section">
          <div class="freight-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#ca8a04"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 .001m9 0H7M13 16V11m0 5h5.5a1.5 1.5 0 001.5-1.5V14m-7-3h7m0 0V8a2 2 0 00-2-2h-5"/></svg>
            Calcular Frete e Prazo
          </div>
          <p class="freight-note">O valor do frete pode variar de acordo com a quantidade de itens.</p>
          <form id="freight-form"
            data-codprod="<?= $pid ?>"
            data-price="<?= htmlspecialchars((string)$price) ?>"
            data-peso="<?= $peso ?>"
            data-altura="<?= $altura ?>"
            data-largura="<?= $largura ?>"
            data-comprimento="<?= $comprimento ?>">
            <div class="freight-input-row">
              <input type="text" id="freight-cep" class="input-field" placeholder="Digite seu CEP"
                     maxlength="9" inputmode="numeric">
              <button type="submit" id="freight-calc-btn" class="freight-calc-btn" disabled>Calcular</button>
            </div>
          </form>
          <div id="freight-result"></div>
        </div>

        <!-- Specs -->
        <?php if (!empty($specs)): ?>
        <div class="specs-box">
          <div class="specs-title">
            <span style="width:.375rem;height:1rem;background:#facc15;border-radius:9999px;display:inline-block;"></span>
            Especificações
          </div>
          <?php foreach ($specs as $spec): ?>
          <div class="spec-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#ca8a04"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span class="text-sm text-gray-700">
              <span class="spec-label"><?= htmlspecialchars($spec['label']) ?>:</span>
              <?= htmlspecialchars($spec['valor']) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Product description -->
    <?php if (!empty($desc)): ?>
    <div class="product-description">
      <h2>Descrição do Produto</h2>
      <p><?= nl2br(htmlspecialchars($desc)) ?></p>
    </div>
    <?php endif; ?>
  </div>

</div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
