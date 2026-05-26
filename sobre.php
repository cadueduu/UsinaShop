<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Sobre Nós';
$page_desc  = 'Conheça a Spark Eletrônica, nossa história, diferenciais e compromisso com qualidade.';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>

<main class="page-content">
  <section class="section-padding">
    <div class="container about-wrap">
      <section class="about-hero">
        <div class="about-hero__content">
          <span class="about-badge">Quem Somos</span>
          <h1>Energia Inteligente para Projetos que Não Podem Parar</h1>
          <p>
            A Spark Eletrônica nasceu para transformar desempenho técnico em resultado real.
            Criamos soluções em fontes, carregadores e energia com foco em confiabilidade, segurança e alta performance.
          </p>
          <div class="about-hero__actions">
            <a href="/products.php" class="btn btn-yellow">Ver Produtos</a>
            <a href="/atendimento.php" class="btn btn-outline about-btn-light">Falar com Especialista</a>
          </div>
        </div>
        <div class="about-hero__image">
          <picture>
            <source type="image/webp" srcset="/assets/images/banners/banner1.webp">
            <img src="/assets/images/banners/banner1.jpg"
                 alt="Produtos Spark"
                 width="2400" height="543"
                 loading="lazy" decoding="async"
                 onerror="this.style.display='none'">
          </picture>
        </div>
      </section>

      <section class="about-metrics">
        <article class="about-metric-card">
          <strong>+1000</strong>
          <span>Itens para diversas aplicações</span>
        </article>
        <article class="about-metric-card">
          <strong>24/7</strong>
          <span>Produtos projetados para uso contínuo</span>
        </article>
        <article class="about-metric-card">
          <strong>Brasil</strong>
          <span>Atendimento e distribuição nacional</span>
        </article>
        <article class="about-metric-card">
          <strong>Suporte</strong>
          <span>Time técnico para orientar cada projeto</span>
        </article>
      </section>

      <section class="about-section">
        <h2>Nossa História</h2>
        <p>
          A evolução da Spark segue uma direção clara: unir tecnologia aplicada, construção robusta e experiência de uso simples.
          Crescemos entendendo a rotina de instaladores, técnicos e clientes finais para entregar produtos que funcionam bem
          no laboratório, na loja, na estrada e no campo.
        </p>
        <p>
          Inspirados pelo ecossistema da Usina Spark, mantemos o compromisso com melhoria contínua, proximidade com o cliente
          e solidez técnica em cada linha desenvolvida.
        </p>
      </section>

      <section class="about-section">
        <h2>Diferenciais Reais</h2>
        <div class="about-grid">
          <article class="about-card about-card--highlight">
            <h3>Engenharia Aplicada</h3>
            <p>Projetos pensados para performance estável, proteção elétrica e longa vida útil.</p>
          </article>
          <article class="about-card about-card--highlight">
            <h3>Design Funcional</h3>
            <p>Produtos com leitura fácil, instalação descomplicada e operação intuitiva no dia a dia.</p>
          </article>
          <article class="about-card about-card--highlight">
            <h3>Parceria de Longo Prazo</h3>
            <p>Atendimento consultivo e suporte para crescimento consistente de quem confia na Spark.</p>
          </article>
        </div>
      </section>

      <section class="about-cta">
        <div class="about-cta__content">
          <h2>Vamos construir seu próximo projeto juntos?</h2>
          <p>
            Se você precisa de orientação para escolher a melhor solução, nosso time pode ajudar.
            Conheça o catálogo completo ou fale com o atendimento.
          </p>
        </div>
        <div class="about-cta__actions">
          <a href="/products.php" class="btn btn-primary">Explorar Catálogo</a>
          <a href="/atendimento.php" class="btn btn-outline">Abrir Atendimento</a>
        </div>
      </section>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
