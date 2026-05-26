<?php
require_once __DIR__ . '/../includes/config.php';

admin_require_basic_auth();

if (($_GET['action'] ?? '') !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    $action = (string)($_GET['action'] ?? '');
    if ($action === 'produto-toggle-sync') {
        $codprod = intval($body['codprod'] ?? 0);
        $syncsite = (string)($body['syncsite'] ?? '');
        if ($codprod <= 0 || !in_array($syncsite, ['S', 'N'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        sb('produto', ['codprod' => 'eq.' . $codprod], 'PATCH', ['syncsite' => $syncsite], SUPABASE_SERVICE_KEY);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$page_num = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset   = ($page_num - 1) * $per_page;

// Fetch products (service key for full access)
$select = 'codprod,descrprod,comnome,syncsite,codgrupoprod,'
        . 'preco(vlr_venda),estoque(estoque_disponivel),produto_imagem(url,ordem)';

$produtos = sb('produto', [
    'select' => $select,
    'order'  => 'codprod.asc',
    'limit'  => $per_page,
    'offset' => $offset,
], 'GET', null, SUPABASE_SERVICE_KEY, ['Range: ' . $offset . '-' . ($offset + $per_page - 1), 'Range-Unit: items', 'Prefer: count=exact']);

$categories = fetch_categories();

// Build flat category name lookup
$cat_names = [];
foreach ($categories as $gid => $g) {
    $cat_names[$gid] = cat_name($g['descr_grupo']);
    foreach ($g['children'] as $sid => $s) {
        $cat_names[$sid] = cat_name($s['descr_grupo']);
    }
}

$admin_page_title = 'Gestão de Produtos';
$admin_active     = 'produtos';
include __DIR__ . '/layout.php';
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <input type="text" id="prod-search" class="admin-search" placeholder="Buscar produto..." oninput="filterProducts()">
  <button class="admin-filter-btn active" onclick="setProdFilter('all',this)">Todos</button>
  <button class="admin-filter-btn" onclick="setProdFilter('S',this)">No Site</button>
  <button class="admin-filter-btn" onclick="setProdFilter('N',this)">Fora do Site</button>
</div>

<!-- Table -->
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th style="width:3.5rem;">Foto</th>
        <th>Nome</th>
        <th>Categoria</th>
        <th>Preço</th>
        <th>Estoque</th>
        <th>Site</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody id="prod-tbody">
      <?php foreach ($produtos as $p):
        $pid   = $p['codprod'];
        $name  = prod_name($p);
        $price = $p['preco'][0]['vlr_venda'] ?? 0;
        $stock = intval($p['estoque'][0]['estoque_disponivel'] ?? 0);
        $img   = prod_img($p['produto_imagem'] ?? []);
        $cat   = $cat_names[$p['codgrupoprod'] ?? 0] ?? '—';
        $sync  = $p['syncsite'] ?? 'N';
        // Stock badge class
        if ($stock > 10) $sbadge = 'badge-green';
        elseif ($stock > 0) $sbadge = 'badge-orange';
        else $sbadge = 'badge-red';
      ?>
      <tr data-name="<?= htmlspecialchars(strtolower($name)) ?>"
          data-sync="<?= htmlspecialchars($sync) ?>"
          data-codprod="<?= $pid ?>">
        <td>
          <img src="<?= htmlspecialchars($img) ?>" class="admin-thumb" onerror="this.src='/assets/images/produtos/logo.png'" alt="">
        </td>
        <td>
          <a href="/product.php?id=<?= $pid ?>" target="_blank"
             style="font-weight:500;font-size:.875rem;color:#111827;max-width:280px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
             title="<?= htmlspecialchars($name) ?>">
            <?= htmlspecialchars($name) ?>
          </a>
          <span style="font-size:.75rem;color:#9ca3af;">ID: <?= $pid ?></span>
        </td>
        <td style="font-size:.8125rem;color:#6b7280;"><?= htmlspecialchars($cat) ?></td>
        <td style="font-size:.875rem;font-weight:600;"><?= $price > 0 ? fmt_brl($price) : '<span style="color:#ef4444">Sem preço</span>' ?></td>
        <td>
          <span class="badge <?= $sbadge ?>"><?= $stock ?> un.</span>
        </td>
        <td>
          <button class="admin-toggle <?= $sync==='S'?'on':'' ?>"
                  id="sync-<?= $pid ?>"
                  onclick="toggleSync(<?= $pid ?>, this)"
                  title="<?= $sync==='S' ? 'Remover do site' : 'Publicar no site' ?>">
          </button>
        </td>
        <td>
          <a href="/admin/produto-edit.php?id=<?= $pid ?>" style="font-size:.75rem;color:#3b82f6;font-weight:500;">Editar</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (empty($produtos)): ?>
    <p style="text-align:center;padding:2rem;color:#6b7280;">Nenhum produto encontrado.</p>
  <?php endif; ?>
</div>

<!-- Pagination -->
<div class="pagination" style="margin-top:1rem;">
  <?php if ($page_num > 1): ?>
    <a href="?page=<?= $page_num-1 ?>">&laquo;</a>
  <?php endif; ?>
  <a href="?page=<?= $page_num ?>" class="current"><?= $page_num ?></a>
  <?php if (count($produtos) >= $per_page): ?>
    <a href="?page=<?= $page_num+1 ?>">&raquo;</a>
  <?php endif; ?>
</div>
<p style="margin-top:.5rem;font-size:.8125rem;color:#9ca3af;"><?= count($produtos) ?> produto(s) nesta página.</p>

<script>
let activeProdFilter = 'all';

function filterProducts() {
  const q = document.getElementById('prod-search').value.toLowerCase();
  document.querySelectorAll('#prod-tbody tr').forEach(row => {
    const name = row.dataset.name || '';
    const sync = row.dataset.sync;
    const matchQ = !q || name.includes(q);
    const matchF = activeProdFilter === 'all' || sync === activeProdFilter;
    row.style.display = matchQ && matchF ? '' : 'none';
  });
}

function setProdFilter(f, btn) {
  activeProdFilter = f;
  document.querySelectorAll('.admin-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  filterProducts();
}

async function toggleSync(codprod, btn) {
  const isOn  = btn.classList.contains('on');
  const newVal = isOn ? 'N' : 'S';
  const res = await fetch(`?action=produto-toggle-sync`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ codprod, syncsite: newVal }) });
  const data = await res.json().catch(() => null);
  if (res.ok) {
    btn.classList.toggle('on');
    const row = btn.closest('tr');
    if (row) row.dataset.sync = newVal;
  } else {
    alert(data?.error || 'Erro ao atualizar produto.');
  }
}
</script>

<?php include __DIR__ . '/layout-end.php'; ?>

