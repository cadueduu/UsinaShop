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
    <!-- Freight calculator (cart-wide) -->
    <div class="freight-section" id="cart-freight-section">
      <div class="freight-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#ca8a04"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 .001m9 0H7M13 16V11m0 5h5.5a1.5 1.5 0 001.5-1.5V14m-7-3h7m0 0V8a2 2 0 00-2-2h-5"/></svg>
        Calcular Frete e Prazo
      </div>
      <form id="cart-freight-form">
        <div class="freight-input-row">
          <input type="text" id="cart-freight-cep" class="input-field" placeholder="Digite seu CEP"
                 maxlength="9" inputmode="numeric" autocomplete="postal-code">
          <button type="submit" id="cart-freight-calc-btn" class="freight-calc-btn" disabled>Calcular</button>
        </div>
      </form>
      <div id="cart-freight-result"></div>
    </div>
    <div class="cart-subtotal">
      <span class="cart-subtotal-label">Subtotal</span>
      <span class="cart-subtotal-val" id="cart-subtotal">R$ 0,00</span>
    </div>
    <button class="cart-checkout-btn" id="cart-checkout-link" type="button">Finalizar Compra →</button>
  </div>
</div>
