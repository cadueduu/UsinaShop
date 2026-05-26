<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Atendimento';
$page_desc  = 'Central única de atendimento para ajuda, garantia, trocas, devoluções e contato.';
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
        <span class="institutional-badge">Atendimento Spark</span>
        <h1>Central de Ajuda</h1>
        <p>
          Abra seu ticket por e-mail de forma rápida e organizada.
          Descreva sua necessidade e nossa equipe retorna com a orientação.
        </p>
      </div>

      <section id="atendimento" class="institutional-section">
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
                  <option value="Garantia">Garantia</option>
                  <option value="Troca e Devoluções">Troca e Devoluções</option>
                  <option value="Suporte">Suporte</option>
                  <option value="Outros">Outros</option>
                </select>
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="ticket-order">Número do Pedido (opcional)</label>
              <input class="input-field" type="text" id="ticket-order" placeholder="Ex.: PED-12345">
            </div>
            <div class="form-field hidden" id="ticket-serial-field">
              <label class="form-label" for="ticket-serial">Número de Série do Produto</label>
              <input class="input-field" type="text" id="ticket-serial" placeholder="Ex.: SN-ABC-12345">
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
    const categoryInput = document.getElementById('ticket-category');
    const serialField = document.getElementById('ticket-serial-field');
    const serialInput = document.getElementById('ticket-serial');

    function categoryNeedsSerial(category) {
      return category === 'Garantia' || category === 'Troca e Devoluções';
    }

    function syncSerialField() {
      const shouldRequire = categoryNeedsSerial(categoryInput.value.trim());
      serialField.classList.toggle('hidden', !shouldRequire);
      serialInput.required = shouldRequire;
      if (!shouldRequire) {
        serialInput.value = '';
      }
    }

    categoryInput.addEventListener('change', syncSerialField);
    syncSerialField();

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      const name = document.getElementById('ticket-name').value.trim();
      const email = document.getElementById('ticket-email').value.trim();
      const phone = document.getElementById('ticket-phone').value.trim();
      const category = document.getElementById('ticket-category').value.trim();
      const order = document.getElementById('ticket-order').value.trim();
      const serial = serialInput.value.trim();
      const subject = document.getElementById('ticket-subject').value.trim();
      const message = document.getElementById('ticket-message').value.trim();

      if (!name || !email || !category || !subject || !message) {
        alert('Preencha os campos obrigatórios para abrir o ticket.');
        return;
      }
      if (categoryNeedsSerial(category) && !serial) {
        alert('Para Garantia e Troca e Devoluções, informe o número de série do produto.');
        return;
      }

      const bodyLines = [
        'Nome: ' + name,
        'E-mail: ' + email,
        'Telefone: ' + (phone || 'Não informado'),
        'Categoria: ' + category,
        'Pedido: ' + (order || 'Não informado'),
        'Número de série: ' + (serial || 'Não informado'),
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
