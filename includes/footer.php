<?php // footer.php — Global site footer ?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">

      <!-- Col 1: Company Info -->
      <div>
        <img src="/assets/images/produtos/logo.png" alt="Spark Eletrônica" class="footer-logo"
             width="423" height="251" loading="lazy" decoding="async" onerror="this.style.display='none'">
        <p class="footer-desc">
          Fornecemos soluções em eletrônica e energia para indústrias, empresas e consumidores finais com qualidade e confiança.
        </p>
      </div>

      <!-- Col 2: Quick Links -->
      <div>
        <h3 class="footer-col-title">Links Rápidos</h3>
        <a href="/sobre.php" class="footer-link">Sobre Nós</a>
        <a href="/products.php" class="footer-link">Produtos</a>
        <a href="/atendimento.php" class="footer-link">Suporte</a>
        <a href="/institucional.php" class="footer-link">Institucional</a>
      </div>

      <!-- Col 3: Support -->
      <div>
        <h3 class="footer-col-title">Atendimento</h3>
        <a href="/atendimento.php" class="footer-link">Central de Ajuda</a>
        <a href="/atendimento.php" class="footer-link">Garantia</a>
        <a href="/atendimento.php" class="footer-link">Trocas e Devoluções</a>
        <a href="/atendimento.php" class="footer-link">Fale Conosco</a>
      </div>

      <!-- Col 4: Social Media -->
      <div>
        <h3 class="footer-col-title">Siga-nos</h3>
        <div class="footer-socials">
          <!-- Facebook -->
          <a href="#" class="footer-social fb" aria-label="Facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
            </svg>
          </a>
          <!-- LinkedIn -->
          <a href="#" class="footer-social ln" aria-label="LinkedIn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/>
            </svg>
          </a>
          <!-- Instagram -->
          <a href="#" class="footer-social ig" aria-label="Instagram">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
            </svg>
          </a>
          <!-- TikTok -->
          <a href="#" class="footer-social tt" aria-label="TikTok">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.3 6.3 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.79 1.53V6.78a4.85 4.85 0 01-1.02-.09z"/>
            </svg>
          </a>
        </div>
      </div>

    </div><!-- /footer-grid -->

    <!-- Bottom bar -->
    <div class="footer-bottom">
      <p>© <?= date('Y') ?> SPARK ELETRÔNICA. Todos os direitos reservados.</p>
      <p>CNPJ: 11.849.626/0001-93 · Sacramento – MG, Brasil</p>
      <p>R. Cel. Zéca de Almeida, 180 - Jardim Alvorada, Sacramento - MG, 38190-000</p>
    </div>
  </div>
</footer>

<div vw class="enabled">
  <div vw-access-button class="active"></div>
  <div vw-plugin-wrapper>
    <div class="vw-plugin-top-wrapper"></div>
  </div>
</div>
<script async src="https://vlibras.gov.br/app/vlibras-plugin.js" onload="new window.VLibras.Widget('https://vlibras.gov.br/app')"></script>
