<?php
// product-card.php — Reusable product card partial
// Expects $p = product array with preco, estoque, produto_imagem
$pid   = $p['codprod'];
$name  = prod_name($p);
$price = $p['preco'][0]['vlr_venda'] ?? 0;
$img   = prod_img($p['produto_imagem'] ?? []);
?>
<div class="product-card">
  <a href="/product.php?id=<?= $pid ?>" class="product-card__img">
    <img src="<?= htmlspecialchars($img) ?>"
         alt="<?= htmlspecialchars($name) ?>"
         width="400" height="400"
         loading="lazy"
         decoding="async"
         onerror="this.src='/assets/images/produtos/logo.png'">
  </a>
  <div class="product-card__body">
    <a href="/product.php?id=<?= $pid ?>" class="product-card__name">
      <?= htmlspecialchars($name) ?>
    </a>
    <div class="product-card__price"><?= fmt_brl($price) ?></div>
    <button
      class="product-card__btn"
      type="button"
      data-add-to-cart="1"
      data-id="<?= $pid ?>"
      data-name="<?= htmlspecialchars($name) ?>"
      data-price="<?= htmlspecialchars((string)$price) ?>"
      data-image="<?= htmlspecialchars($img) ?>"
      data-qty="1"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      Adicionar
    </button>
  </div>
</div>

