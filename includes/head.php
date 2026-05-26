<?php
// head.php — Common <head> block for all pages
$page_title = isset($page_title) ? $page_title . ' | Spark Eletrônica' : 'Spark Eletrônica';
$_css_v = @filemtime(dirname(__DIR__) . '/assets/css/style.css') ?: '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($page_desc ?? 'Spark Eletrônica - Soluções em eletrônica e energia') ?>">
  <link rel="icon" href="/assets/images/produtos/logo.png" type="image/png">

  <!-- Resource hints: early connections to external origins -->
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<?php if (defined('SUPABASE_URL') && SUPABASE_URL !== ''): ?>
  <link rel="preconnect" href="<?= htmlspecialchars(SUPABASE_URL) ?>" crossorigin>
<?php endif; ?>
  <link rel="dns-prefetch" href="https://vlibras.gov.br">
<?php if (!empty($preload_image)): ?>
  <?php
    /* Preload the LCP image. Two shapes supported:
       a) WebP + JPG fallback pair (homepage banner): $preload_image=webp URL,
          $preload_image_jpg=jpg URL → emits href=jpg + imagesrcset=webp + type=image/webp.
       b) Single proxied image (product detail card): only $preload_image set →
          emits href=URL with no imagesrcset/type (avoids type-mismatch warnings).
       fetchpriority="high" is emitted ONLY when the source URL passes the
       low-resolution check — local site assets and verified low-res variants
       qualify; any URL marked or unmarkable as low is downgraded (omits the
       attribute and lets the browser choose). */
    $_pre_href    = $preload_image_jpg ?? $preload_image;
    $_pre_srcset  = !empty($preload_image_jpg) ? $preload_image : '';
    $_pre_type    = $preload_image_type ?? ($_pre_srcset !== '' ? 'image/webp' : '');
    $_pre_high    = function_exists('is_low_resolution_image_url')
                  && is_low_resolution_image_url($_pre_href);
  ?>
  <link rel="preload" as="image"
        href="<?= htmlspecialchars($_pre_href) ?>"
        <?php if ($_pre_srcset !== ''): ?>imagesrcset="<?= htmlspecialchars($_pre_srcset) ?>"<?php endif; ?>
        <?php if ($_pre_type !== ''): ?>type="<?= htmlspecialchars($_pre_type) ?>"<?php endif; ?>
        <?php if ($_pre_high): ?>fetchpriority="high"<?php endif; ?>
        <?php if (!empty($preload_image_media)): ?>media="<?= htmlspecialchars($preload_image_media) ?>"<?php endif; ?>>
<?php endif; ?>

  <!-- Styles (fingerprinted for cache-busting) -->
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= $_css_v ?>">

  <!-- Supabase JS v2 (defer: non-render-blocking; executes before DOMContentLoaded) -->
  <script defer src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <script>
    window.APP_LOCAL_MODE  = <?= defined('LOCAL_DATA_MODE') && LOCAL_DATA_MODE ? 'true' : 'false' ?>;
    window.APP_SB_URL      = <?= json_encode(SUPABASE_URL) ?>;
    window.APP_SB_ANON     = <?= json_encode(SUPABASE_ANON_KEY) ?>;
    window.APP_CEP_ORIGEM  = <?= json_encode(defined('CEP_ORIGEM') ? CEP_ORIGEM : '') ?>;
  </script>
</head>
<body>
  <a class="skip-link" href="#page-content">Pular para o conteúdo</a>
