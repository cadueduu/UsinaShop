<?php // cart-sidebar.php — Global cart drawer (populated by JS) ?>
<!-- Overlay -->
<div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

<!-- Cart Sidebar -->
<div id="cart-sidebar" class="sidebar" role="dialog" aria-modal="true" aria-labelledby="cart-sidebar-title" aria-hidden="true">
  <div class="sidebar-header">
    <div class="sidebar-title" id="cart-sidebar-title">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
      Seu Carrinho
    </div>
    <button id="cart-close-btn" class="sidebar-close" type="button" aria-label="Fechar carrinho">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="sidebar-body" id="cart-body">
    <!-- Populated by main.js -->
  </div>
  <div class="sidebar-footer" id="cart-footer" style="display:none;">
    <div class="cart-subtotal">
      <span class="cart-subtotal-label">Subtotal</span>
      <span class="cart-subtotal-val" id="cart-subtotal">R$ 0,00</span>
    </div>
    <button class="cart-checkout-btn" id="cart-checkout-link" type="button">Finalizar Compra →</button>
  </div>
</div>
