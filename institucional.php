<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Institucional e Atendimento';
$page_desc  = 'Conheça a Spark Eletrônica, políticas e canais de atendimento para abertura de ticket por e-mail.';
$ticket_email = 'sac@sparkpower.com.br';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/cart-sidebar.php';
include __DIR__ . '/includes/search-sidebar.php';
include __DIR__ . '/includes/mobile-bar.php';
?>

<main class="page-content">
  <section class="section-padding">
    <div class="container institutional-wrap">
      <div class="institutional-hero">
        <span class="institutional-badge">Institucional Spark</span>
        <h1>Sobre Nós e Atendimento Geral</h1>
        <p>
          A Spark Eletrônica desenvolve fontes, carregadores e soluções para energia com foco em desempenho,
          confiabilidade e excelente custo-benefício. Nosso compromisso é atender desde aplicações do dia a dia
          até operações profissionais com suporte próximo e agilidade.
        </p>
      </div>

      <section id="sobre" class="institutional-section">
        <h2>Sobre a Spark</h2>
        <p>
          Inspirada na comunicação institucional da Usina Spark, nossa proposta é entregar produtos duráveis,
          com tecnologia atual e alta disponibilidade. Trabalhamos para manter uma linha ampla de soluções em
          eletrônica e energia, com qualidade de fabricação e atendimento técnico dedicado.
        </p>
      </section>

      <section id="institucional" class="institutional-section">
        <h2>Institucional</h2>
        <div class="institutional-grid">
          <article class="institutional-card">
            <h3>Missão</h3>
            <p>
              Levar soluções confiáveis em eletrônica e energia para consumidores, empresas e aplicações
              industriais em todo o Brasil.
            </p>
          </article>
          <article class="institutional-card">
            <h3>Visão</h3>
            <p>
              Ser referência nacional em performance, durabilidade e suporte no segmento de fontes,
              carregadores e inversores.
            </p>
          </article>
          <article class="institutional-card">
            <h3>Valores</h3>
            <p>
              Qualidade, transparência, inovação, foco no cliente e melhoria contínua em produtos e serviços.
            </p>
          </article>
        </div>
      </section>

      <section id="atendimento" class="institutional-section">
        <h2>Aba Geral de Atendimento</h2>
        <p class="institutional-lead">
          Esta área unifica Central de Ajuda, Garantia, Trocas e Devoluções e Fale Conosco.
          Abra seu ticket por e-mail usando o formulário abaixo.
        </p>

        <div class="support-grid">
          <article id="central-ajuda" class="support-card">
            <h3>Central de Ajuda</h3>
            <p>
              Dicas de instalação, operação e uso seguro dos produtos. Se precisar de orientação técnica,
              abra um ticket com o máximo de detalhes.
            </p>
          </article>
          <article id="garantia" class="support-card">
            <h3>Garantia</h3>
            <p>
              Informe número de série, nota fiscal e descrição da ocorrência para análise. Nosso time retorna
              com os próximos passos e prazo estimado.
            </p>
          </article>
          <article id="trocas-devolucoes" class="support-card">
            <h3>Trocas e Devoluções</h3>
            <p>
              Para solicitações de troca/devolução, envie dados do pedido, motivo e evidências (quando houver),
              para triagem e orientação de procedimento.
            </p>
          </article>
          <article id="fale-conosco" class="support-card">
            <h3>Fale Conosco</h3>
            <p>
              Atendimento pelo e-mail <strong><?= htmlspecialchars($ticket_email) ?></strong>.
              Use o formulário para abrir seu ticket de forma padronizada.
            </p>
          </article>
        </div>

        <div class="ticket-box">
          <h3>Abrir Ticket por E-mail</h3>
          <p>Ao enviar, seu aplicativo de e-mail será aberto com o ticket preenchido.</p>

          <form id="ticket-form" class="ticket-form">
            <div class="ticket-row">
              <div class="form-field">
                <label class="form-label" for="ticket-name">Nome</label>
                <input class="input-field" type="text" id="ticket-name" required>
              </div>
              <div class="form-field">
                <label class="form-label" for="ticket-email">E-mail</label>
                <input class="input-field" type="email" id="ticket-email" required>
              </div>
            </div>
            <div class="ticket-row">
              <div class="form-field">
                <label class="form-label" for="ticket-phone">Telefone</label>
                <input class="input-field" type="text" id="ticket-phone" placeholder="(00) 00000-0000">
              </div>
              <div class="form-field">
                <label class="form-label" for="ticket-category">Categoria</label>
                <select class="input-field" id="ticket-category" required>
                  <option value="">Selecione</option>
                  <option value="Central de Ajuda">Central de Ajuda</option>
                  <option value="Garantia">Garantia</option>
                  <option value="Trocas e Devoluções">Trocas e Devoluções</option>
                  <option value="Fale Conosco">Fale Conosco</option>
                </select>
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="ticket-order">Número do Pedido (opcional)</label>
              <input class="input-field" type="text" id="ticket-order" placeholder="Ex.: PED-12345">
            </div>
            <div class="form-field">
              <label class="form-label" for="ticket-subject">Assunto</label>
              <input class="input-field" type="text" id="ticket-subject" required>
            </div>
            <div class="form-field">
              <label class="form-label" for="ticket-message">Mensagem</label>
              <textarea class="input-field" id="ticket-message" rows="6" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Enviar Ticket por E-mail</button>
          </form>
        </div>
      </section>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  (function () {
    const form = document.getElementById('ticket-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      const name = document.getElementById('ticket-name').value.trim();
      const email = document.getElementById('ticket-email').value.trim();
      const phone = document.getElementById('ticket-phone').value.trim();
      const category = document.getElementById('ticket-category').value.trim();
      const order = document.getElementById('ticket-order').value.trim();
      const subject = document.getElementById('ticket-subject').value.trim();
      const message = document.getElementById('ticket-message').value.trim();

      if (!name || !email || !category || !subject || !message) {
        alert('Preencha os campos obrigatórios para abrir o ticket.');
        return;
      }

      const bodyLines = [
        'Nome: ' + name,
        'E-mail: ' + email,
        'Telefone: ' + (phone || 'Não informado'),
        'Categoria: ' + category,
        'Pedido: ' + (order || 'Não informado'),
        '',
        'Mensagem:',
        message
      ];

      const mailto = 'mailto:<?= rawurlencode($ticket_email) ?>'
        + '?subject=' + encodeURIComponent('[Ticket Site] ' + subject)
        + '&body=' + encodeURIComponent(bodyLines.join('\n'));

      window.location.href = mailto;
    });
  })();
</script>

<script defer src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
