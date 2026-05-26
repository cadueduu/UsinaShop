<?php // search-sidebar.php — Global search drawer ?>
<div id="search-sidebar" class="sidebar" role="dialog" aria-modal="true" aria-labelledby="search-sidebar-title" aria-hidden="true">
  <div class="sidebar-header">
    <div class="sidebar-title" id="search-sidebar-title">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.15 4.15a7.5 7.5 0 0012.5 12.5z"/></svg>
      Buscar Produtos
    </div>
    <button id="search-close-btn" class="sidebar-close" type="button" aria-label="Fechar busca">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="search-input-row">
    <label class="sr-only" for="search-input">Buscar produtos</label>
    <input type="text" id="search-input" class="input-field" placeholder="O que você procura?" autocomplete="off">
    <button id="search-submit" class="search-submit-btn" type="button">Buscar</button>
  </div>
  <div class="search-cats">
    <p class="search-cats-title">Categorias</p>
    <div id="search-cat-list">
      <?php for($i=0;$i<6;$i++): ?>
        <div class="skeleton" style="height:2.75rem;border-radius:.5rem;margin-bottom:.5rem;"></div>
      <?php endfor; ?>
    </div>
  </div>
</div>
