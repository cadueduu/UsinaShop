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

// Carrega TODOS os produtos em lotes de 1000 (limite máximo do Supabase REST).
$produtos   = [];
$batch_size = 1000;
$offset     = 0;
do {
    $rows = sb('produto', [
        'select' => 'codprod,descrprod,comnome,syncsite,codgrupoprod,peso,altura,largura,comprimento,desccurta',
        'order'  => 'codprod.asc',
        'limit'  => $batch_size,
        'offset' => $offset,
    ], 'GET', null, SUPABASE_SERVICE_KEY);
    if (!is_array($rows) || empty($rows)) break;
    $produtos = array_merge($produtos, $rows);
    $offset += $batch_size;
    if (count($rows) < $batch_size) break;
} while ($offset < 50000);

// Busca preço, estoque e imagens em batches paralelos
$preco_map = [];
$estoque_map = [];
$img_map = [];
if (!empty($produtos)) {
    $ids = array_map(fn($p) => intval($p['codprod']), $produtos);
    foreach (array_chunk($ids, 500) as $chunk) {
        $in_clause = 'in.(' . implode(',', $chunk) . ')';
        $batch = sb_multi([
            'preco'   => ['preco',   ['codprod' => $in_clause, 'select' => 'codprod,vlr_venda', 'limit' => 1000], 'GET', null, SUPABASE_SERVICE_KEY],
            'estoque' => ['estoque', ['codprod' => $in_clause, 'select' => 'codprod,estoque_disponivel', 'limit' => 1000], 'GET', null, SUPABASE_SERVICE_KEY],
        ]);
        foreach ((array)($batch['preco'] ?? []) as $r) {
            $preco_map[(int)$r['codprod']] = (float)($r['vlr_venda'] ?? 0);
        }
        foreach ((array)($batch['estoque'] ?? []) as $r) {
            $estoque_map[(int)$r['codprod']] = (int)($r['estoque_disponivel'] ?? 0);
        }
        $imgs = db_products_images_low_bulk($chunk);
        foreach ($imgs as $cp => $list) {
            $img_map[(int)$cp] = $list;
        }
    }
}

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
  <button class="admin-filter-btn" onclick="setProdFilter('ready',this)">Prontos</button>
  <button class="admin-filter-btn" onclick="setProdFilter('not_ready',this)">Pendentes</button>
</div>

<style>
.req-wrap{position:relative;display:inline-flex;align-items:center}
.req-chip{display:inline-flex;align-items:center;gap:.4rem;cursor:default}
.req-panel{display:none;position:absolute;top:calc(100% + .5rem);left:0;z-index:50;min-width:220px;background:#111827;color:#f9fafb;border:1px solid rgba(255,255,255,.08);border-radius:.75rem;padding:.6rem .65rem;box-shadow:0 16px 40px rgba(0,0,0,.25)}
.req-wrap:hover .req-panel{display:block}
.req-row{display:flex;align-items:center;gap:.5rem;padding:.25rem 0;font-size:.75rem;white-space:nowrap}
.req-dot{width:.65rem;height:.65rem;border-radius:9999px;display:inline-block;flex:0 0 auto}
.req-ok{background:#22c55e}
.req-no{background:#ef4444}
.req-title{font-size:.7rem;letter-spacing:.03em;text-transform:uppercase;color:rgba(255,255,255,.7);margin-bottom:.35rem}
</style>

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
        $pid   = intval($p['codprod']);
        $name  = prod_name($p);
        $price = $preco_map[$pid] ?? 0;
        $stock = $estoque_map[$pid] ?? 0;
        $img   = prod_img($img_map[$pid] ?? []);
        $cat   = $cat_names[$p['codgrupoprod'] ?? 0] ?? '—';
        $sync  = $p['syncsite'] ?? 'N';
        $has_photo = $img !== '/assets/images/produtos/logo.png';
        $peso = (float)($p['peso'] ?? 0);
        $altura = (float)($p['altura'] ?? 0);
        $largura = (float)($p['largura'] ?? 0);
        $comprimento = (float)($p['comprimento'] ?? 0);
        $has_dims = $peso > 0 && $altura > 0 && $largura > 0 && $comprimento > 0;
        $has_desc = trim((string)($p['desccurta'] ?? '')) !== '';
        $has_price = $price > 0;
        $has_stock = $stock > 0;
        $ready = $has_photo && $has_dims && $has_desc && $has_price && $has_stock;
        $req_badge = $ready ? 'badge-green' : 'badge-red';
        $req_text  = $ready ? 'OK' : 'Revisar';
        // Stock badge class
        if ($stock > 10) $sbadge = 'badge-green';
        elseif ($stock > 0) $sbadge = 'badge-orange';
        else $sbadge = 'badge-red';
      ?>
      <tr data-name="<?= htmlspecialchars(strtolower($name)) ?>"
          data-sync="<?= htmlspecialchars($sync) ?>"
          data-photo="<?= $has_photo ? '1' : '0' ?>"
          data-dims="<?= $has_dims ? '1' : '0' ?>"
          data-desc="<?= $has_desc ? '1' : '0' ?>"
          data-price="<?= $has_price ? '1' : '0' ?>"
          data-stock="<?= $has_stock ? '1' : '0' ?>"
          data-ready="<?= $ready ? '1' : '0' ?>"
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
          <div style="display:flex;align-items:center;gap:.75rem;">
            <div class="req-wrap">
              <span class="badge <?= $req_badge ?> req-chip">Requisitos: <?= $req_text ?></span>
              <div class="req-panel" role="tooltip">
                <div class="req-title">Checklist</div>
                <div class="req-row"><span class="req-dot <?= $has_photo ? 'req-ok' : 'req-no' ?>"></span>Foto</div>
                <div class="req-row"><span class="req-dot <?= $has_dims ? 'req-ok' : 'req-no' ?>"></span>Peso e medidas</div>
                <div class="req-row"><span class="req-dot <?= $has_desc ? 'req-ok' : 'req-no' ?>"></span>Descrição</div>
                <div class="req-row"><span class="req-dot <?= $has_price ? 'req-ok' : 'req-no' ?>"></span>Preço</div>
                <div class="req-row"><span class="req-dot <?= $has_stock ? 'req-ok' : 'req-no' ?>"></span>Estoque</div>
              </div>
            </div>
            <span class="badge <?= $sync === 'S' ? 'badge-green' : 'badge-red' ?>" data-sync-status title="<?= $sync === 'S' ? 'Publicado' : 'Desativado' ?>"><?= $sync === 'S' ? 'Publicado' : 'Desativado' ?></span>
            <button class="admin-toggle <?= $sync==='S'?'on':'' ?>"
                    id="sync-<?= $pid ?>"
                    onclick="toggleSync(<?= $pid ?>, this)"
                    title="<?= $sync==='S' ? 'Remover do site' : 'Publicar no site' ?>">
            </button>
          </div>
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

<p style="margin-top:.5rem;font-size:.8125rem;color:#9ca3af;"><?= count($produtos) ?> produto(s) no total.</p>

<script>
let activeProdFilter = 'all';

function filterProducts() {
  const q = document.getElementById('prod-search').value.toLowerCase();
  document.querySelectorAll('#prod-tbody tr').forEach(row => {
    const name = row.dataset.name || '';
    const ready = row.dataset.ready || '0';
    const matchQ = !q || name.includes(q);
    const matchF =
      activeProdFilter === 'all' ||
      (activeProdFilter === 'ready' && ready === '1') ||
      (activeProdFilter === 'not_ready' && ready === '0');
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
    if (row) {
      row.dataset.sync = newVal;
      const syncBadge = row.querySelector('[data-sync-status]');
      if (syncBadge) {
        syncBadge.classList.remove('badge-green', 'badge-red');
        syncBadge.classList.add(newVal === 'S' ? 'badge-green' : 'badge-red');
        syncBadge.textContent = newVal === 'S' ? 'Publicado' : 'Desativado';
        syncBadge.title = newVal === 'S' ? 'Publicado' : 'Desativado';
      }
    }
  } else {
    alert(data?.error || 'Erro ao atualizar produto.');
  }
}
</script>

<?php include __DIR__ . '/layout-end.php'; ?>
