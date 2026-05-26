<?php
require_once __DIR__ . '/../includes/config.php';

admin_require_basic_auth();

if (($_GET['action'] ?? '') !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    $action = (string)($_GET['action'] ?? '');
    if ($action === 'client-update') {
        $id = trim((string)($body['id'] ?? ''));
        $field = (string)($body['field'] ?? '');
        $value = trim((string)($body['value'] ?? ''));
        if ($id === '' || !in_array($field, ['nome', 'telefone'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        sb('cliente', ['id' => 'eq.' . $id], 'PATCH', [$field => $value], SUPABASE_SERVICE_KEY);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'client-toggle-admin') {
        $id = trim((string)($body['id'] ?? ''));
        $isAdmin = (bool)($body['is_admin'] ?? false);
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        sb('cliente', ['id' => 'eq.' . $id], 'PATCH', ['is_admin' => $isAdmin], SUPABASE_SERVICE_KEY);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$clientes = sb('cliente', ['select'=>'id,nome,email,telefone,cpf_cnpj,is_admin,codparc','order'=>'nome.asc','limit'=>200], 'GET', null, SUPABASE_SERVICE_KEY);

$admin_page_title = 'Gestão de Clientes';
$admin_active     = 'clientes';
include __DIR__ . '/layout.php';
?>

<!-- Search & filters toolbar -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <input type="text" id="client-search" class="admin-search" placeholder="Buscar cliente..." oninput="filterClients()">
  <button class="admin-filter-btn active" data-filter="all" onclick="setFilter('all',this)">Todos</button>
  <button class="admin-filter-btn" data-filter="admin" onclick="setFilter('admin',this)">Admins</button>
</div>

<!-- Table -->
<div class="admin-table-wrap">
  <table class="admin-table" id="clients-table">
    <thead>
      <tr>
        <th onclick="sortTable('nome')">Nome ↕</th>
        <th onclick="sortTable('email')">E-mail ↕</th>
        <th onclick="sortTable('telefone')">Telefone</th>
        <th onclick="sortTable('cpf_cnpj')">CPF/CNPJ</th>
        <th onclick="sortTable('is_admin')">Admin</th>
      </tr>
    </thead>
    <tbody id="clients-tbody">
      <?php foreach ($clientes as $c): ?>
      <tr data-nome="<?= htmlspecialchars(strtolower($c['nome'] ?? '')) ?>"
          data-email="<?= htmlspecialchars(strtolower($c['email'] ?? '')) ?>"
          data-admin="<?= $c['is_admin'] ? '1' : '0' ?>"
          data-id="<?= htmlspecialchars($c['id']) ?>">
        <td>
          <div id="view-name-<?= $c['id'] ?>">
            <?= htmlspecialchars($c['nome'] ?? '—') ?>
            <button onclick="editCell('name','<?= $c['id'] ?>')" style="color:#9ca3af;margin-left:.5rem;font-size:.75rem;" title="Editar">✎</button>
          </div>
          <div id="edit-name-<?= $c['id'] ?>" style="display:none;">
            <input type="text" class="admin-edit-input" id="inp-name-<?= $c['id'] ?>" value="<?= htmlspecialchars($c['nome'] ?? '') ?>" style="width:160px;">
            <button onclick="saveCell('name','<?= $c['id'] ?>')" style="color:#16a34a;font-size:.75rem;margin-left:.25rem;">✔</button>
            <button onclick="cancelCell('name','<?= $c['id'] ?>')" style="color:#ef4444;font-size:.75rem;margin-left:.25rem;">✖</button>
          </div>
        </td>
        <td style="color:#6b7280;"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
        <td>
          <div id="view-phone-<?= $c['id'] ?>"><?= htmlspecialchars($c['telefone'] ?? '—') ?> <button onclick="editCell('phone','<?= $c['id'] ?>')" style="color:#9ca3af;font-size:.75rem;" title="Editar">✎</button></div>
          <div id="edit-phone-<?= $c['id'] ?>" style="display:none;">
            <input type="text" class="admin-edit-input" id="inp-phone-<?= $c['id'] ?>" value="<?= htmlspecialchars($c['telefone'] ?? '') ?>" style="width:140px;">
            <button onclick="saveCell('phone','<?= $c['id'] ?>')" style="color:#16a34a;font-size:.75rem;margin-left:.25rem;">✔</button>
            <button onclick="cancelCell('phone','<?= $c['id'] ?>')" style="color:#ef4444;font-size:.75rem;margin-left:.25rem;">✖</button>
          </div>
        </td>
        <td style="font-family:monospace;font-size:.8125rem;"><?= htmlspecialchars($c['cpf_cnpj'] ?? '—') ?></td>
        <td>
          <button class="admin-toggle <?= $c['is_admin'] ? 'on' : '' ?>"
                  id="toggle-admin-<?= $c['id'] ?>"
                  onclick="toggleAdmin('<?= $c['id'] ?>', this)"
                  title="<?= $c['is_admin'] ? 'Revogar admin' : 'Tornar admin' ?>">
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (empty($clientes)): ?>
    <p style="text-align:center;padding:2rem;color:#6b7280;">Nenhum cliente encontrado.</p>
  <?php endif; ?>
</div>
<p style="margin-top:.75rem;font-size:.8125rem;color:#9ca3af;"><?= count($clientes) ?> cliente(s) no total.</p>

<script>
let sortDir = {};
let activeFilter = 'all';

function filterClients() {
  const q = document.getElementById('client-search').value.toLowerCase();
  document.querySelectorAll('#clients-tbody tr').forEach(row => {
    const nome  = row.dataset.nome  || '';
    const email = row.dataset.email || '';
    const admin = row.dataset.admin;
    const matchSearch = !q || nome.includes(q) || email.includes(q);
    const matchFilter = activeFilter === 'all' || (activeFilter === 'admin' && admin === '1');
    row.style.display = matchSearch && matchFilter ? '' : 'none';
  });
}

function setFilter(f, btn) {
  activeFilter = f;
  document.querySelectorAll('.admin-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  filterClients();
}

function sortTable(col) {
  sortDir[col] = !sortDir[col];
  const tbody = document.getElementById('clients-tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  rows.sort((a, b) => {
    const av = (a.dataset[col] || '').toLowerCase();
    const bv = (b.dataset[col] || '').toLowerCase();
    return sortDir[col] ? av.localeCompare(bv) : bv.localeCompare(av);
  });
  rows.forEach(r => tbody.appendChild(r));
}

function editCell(type, id) {
  document.getElementById(`view-${type}-${id}`).style.display = 'none';
  document.getElementById(`edit-${type}-${id}`).style.display = 'inline-flex';
  document.getElementById(`inp-${type}-${id}`)?.focus();
}

function cancelCell(type, id) {
  document.getElementById(`view-${type}-${id}`).style.display = '';
  document.getElementById(`edit-${type}-${id}`).style.display = 'none';
}

async function saveCell(type, id) {
  const input = document.getElementById(`inp-${type}-${id}`);
  const val = input.value.trim();
  const field = type === 'name' ? 'nome' : 'telefone';
  const res = await fetch(`?action=client-update`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, field, value: val }) });
  const data = await res.json().catch(() => null);
  if (res.ok) {
    const viewDiv = document.getElementById(`view-${type}-${id}`);
    viewDiv.childNodes[0].textContent = val || '—';
    cancelCell(type, id);
  } else {
    alert(data?.error || 'Erro ao salvar.');
  }
}

async function toggleAdmin(id, btn) {
  const isOn = btn.classList.contains('on');
  const res = await fetch(`?action=client-toggle-admin`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, is_admin: !isOn }) });
  const data = await res.json().catch(() => null);
  if (res.ok) { btn.classList.toggle('on'); }
  else { alert(data?.error || 'Erro ao atualizar.'); }
}
</script>

<?php include __DIR__ . '/layout-end.php'; ?>
