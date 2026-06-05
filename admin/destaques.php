<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/featured.php';

admin_require_basic_auth();

// AJAX: alternar destaque
if (($_GET['action'] ?? '') === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body    = json_decode(file_get_contents('php://input'), true);
    $codprod = intval($body['codprod'] ?? 0);
    if ($codprod <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'codprod inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $on = featured_toggle($codprod);
    echo json_encode(['ok' => true, 'featured' => $on], JSON_UNESCAPED_UNICODE);
    exit;
}

// Carrega TODOS os produtos (independente de syncsite), em lotes de 1000.
$all = [];
$batch_size = 1000;
$offset = 0;
do {
    $rows = sb('produto', [
        'select' => 'codprod,descrprod,comnome,syncsite',
        'order'  => 'descrprod.asc',
        'limit'  => $batch_size,
        'offset' => $offset,
    ], 'GET', null, SUPABASE_SERVICE_KEY);
    if (!is_array($rows) || empty($rows)) break;
    $all = array_merge($all, $rows);
    $offset += $batch_size;
    if (count($rows) < $batch_size) break;
} while ($offset < 50000); // safety cap

// Busca estoque e imagens em batch
$estoque_map = [];
$img_map     = [];
if (!empty($all)) {
    $ids = array_map(fn($p) => intval($p['codprod']), $all);
    foreach (array_chunk($ids, 500) as $chunk) {
        $est = sb('estoque', [
            'select'  => 'codprod,estoque_disponivel',
            'codprod' => 'in.(' . implode(',', $chunk) . ')',
            'limit'   => 1000,
        ], 'GET', null, SUPABASE_SERVICE_KEY);
        foreach ((array)$est as $r) {
            $estoque_map[(int)$r['codprod']] = (int)($r['estoque_disponivel'] ?? 0);
        }
        $imgs = db_products_images_low_bulk($chunk);
        foreach ($imgs as $cp => $list) {
            if (!empty($list)) $img_map[(int)$cp] = $list;
        }
    }
}

// Filtra: só produtos que têm foto cadastrada
$all = array_values(array_filter($all, fn($p) => isset($img_map[(int)$p['codprod']])));

$featured_set = array_flip(featured_get_codprods());
$total_produtos    = count($all);
$total_publicados  = 0;
$total_com_estoque = 0;
foreach ($all as $p) {
    if (($p['syncsite'] ?? 'N') === 'S') $total_publicados++;
    if (($estoque_map[(int)$p['codprod']] ?? 0) > 0) $total_com_estoque++;
}

$admin_page_title = 'Produtos em Destaque';
$admin_active     = 'destaques';
include __DIR__ . '/layout.php';
?>

<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
  <input type="text" id="dest-search" class="admin-search" placeholder="Buscar por código ou nome..." oninput="filterDest()" style="min-width:280px;">
  <button class="admin-filter-btn active" onclick="setDestFilter('all',this)">Todos</button>
  <button class="admin-filter-btn" onclick="setDestFilter('estoque',this)">Com estoque</button>
  <button class="admin-filter-btn" onclick="setDestFilter('featured',this)">Em destaque</button>
</div>
<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;font-size:.8125rem;color:#6b7280;">
  <span><strong><?= $total_produtos ?></strong> produtos no total</span>
  <span><strong><?= $total_publicados ?></strong> publicados no site</span>
  <span><strong><?= $total_com_estoque ?></strong> com estoque</span>
  <span id="dest-count"><strong><?= count($featured_set) ?></strong> marcados como destaque</span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th style="width:5rem;">Destaque</th>
        <th style="width:8rem;">Código</th>
        <th>Nome</th>
        <th style="width:7rem;">Estoque</th>
      </tr>
    </thead>
    <tbody id="dest-tbody">
      <?php foreach ($all as $p):
        $pid   = intval($p['codprod']);
        $name  = prod_name($p);
        $on    = isset($featured_set[$pid]);
        $stock = $estoque_map[$pid] ?? 0;
        $sync  = ($p['syncsite'] ?? 'N') === 'S' ? 'S' : 'N';
        if ($stock > 10)     $sbadge = 'badge-green';
        elseif ($stock > 0)  $sbadge = 'badge-orange';
        else                 $sbadge = 'badge-red';
      ?>
      <tr data-name="<?= htmlspecialchars(strtolower($name . ' ' . $pid)) ?>"
          data-featured="<?= $on ? '1' : '0' ?>"
          data-sync="<?= $sync ?>"
          data-stock="<?= $stock ?>">
        <td>
          <button type="button"
                  class="heart-btn <?= $on ? 'on' : '' ?>"
                  onclick="toggleFeatured(<?= $pid ?>, this)"
                  title="<?= $on ? 'Remover dos destaques' : 'Marcar como destaque' ?>"
                  aria-pressed="<?= $on ? 'true' : 'false' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </button>
        </td>
        <td style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.875rem;color:#6b7280;"><?= $pid ?></td>
        <td>
          <a href="/product.php?id=<?= $pid ?>" target="_blank"
             style="font-weight:500;font-size:.875rem;color:#111827;"
             title="<?= htmlspecialchars($name) ?>">
            <?= htmlspecialchars($name) ?>
          </a>
        </td>
        <td>
          <span class="badge <?= $sbadge ?>"><?= $stock ?> un.</span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (empty($all)): ?>
    <p style="text-align:center;padding:2rem;color:#6b7280;">Nenhum produto encontrado.</p>
  <?php endif; ?>
</div>

<style>
.heart-btn {
  background:none; border:none; cursor:pointer; padding:.25rem;
  color:#d1d5db; transition:transform .15s, color .2s;
  display:inline-flex; align-items:center; justify-content:center;
  border-radius:50%;
}
.heart-btn:hover { color:#fca5a5; transform:scale(1.1); }
.heart-btn.on { color:#ef4444; }
.heart-btn.on:hover { color:#dc2626; }
.heart-btn.pulse { animation:heartPulse .35s ease; }
@keyframes heartPulse {
  0% { transform:scale(1); }
  50% { transform:scale(1.35); }
  100% { transform:scale(1); }
}
</style>

<script>
let activeDestFilter = 'all';

function filterDest() {
  const q = (document.getElementById('dest-search').value || '').toLowerCase().trim();
  document.querySelectorAll('#dest-tbody tr').forEach(row => {
    const name  = row.dataset.name || '';
    const fav   = row.dataset.featured;
    const sync  = row.dataset.sync;
    const stock = parseInt(row.dataset.stock || '0', 10);
    const matchQ = !q || name.includes(q);
    let matchF = true;
    if (activeDestFilter === 'estoque')  matchF = stock > 0;
    if (activeDestFilter === 'featured') matchF = fav === '1';
    row.style.display = (matchQ && matchF) ? '' : 'none';
  });
}

function setDestFilter(f, btn) {
  activeDestFilter = f;
  document.querySelectorAll('.admin-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  filterDest();
}

async function toggleFeatured(codprod, btn) {
  btn.disabled = true;
  try {
    const res = await fetch('?action=toggle', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codprod })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Erro');
    btn.classList.toggle('on', data.featured);
    btn.classList.remove('pulse');
    void btn.offsetWidth;
    btn.classList.add('pulse');
    const row = btn.closest('tr');
    if (row) row.dataset.featured = data.featured ? '1' : '0';
    btn.setAttribute('aria-pressed', data.featured ? 'true' : 'false');
    btn.title = data.featured ? 'Remover dos destaques' : 'Marcar como destaque';
    const total = document.querySelectorAll('#dest-tbody tr[data-featured="1"]').length;
    const counter = document.getElementById('dest-count');
    if (counter) counter.innerHTML = '<strong>' + total + '</strong> marcados como destaque';
  } catch (e) {
    alert(e.message || 'Erro ao atualizar destaque.');
  } finally {
    btn.disabled = false;
  }
}

</script>

<?php include __DIR__ . '/layout-end.php'; ?>
