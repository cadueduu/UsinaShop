<?php
require_once __DIR__ . '/../includes/config.php';

admin_require_basic_auth();

$codprod = intval($_GET['id'] ?? 0);
if ($codprod <= 0) {
    header('Location: /admin/produtos.php');
    exit;
}

if (($_GET['action'] ?? '') !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    $action = (string)($_GET['action'] ?? '');
    if ($action === 'image-cache-clear') {
        // No-op since the on-disk image cache was retired: images are now read
        // directly from ext_product_images and served via Supabase's CDN, which
        // keys on the `?v=<updated_at>` cache-buster baked into each URL.
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'produto-save') {
        $payload = [
            'comnome' => (string)($body['comnome'] ?? ''),
            'syncsite' => (string)($body['syncsite'] ?? ''),
            'desccurta' => (string)($body['desccurta'] ?? ''),
            'descrprodoed' => (string)($body['descrprodoed'] ?? ''),
            'peso' => floatval($body['peso'] ?? 0),
            'altura' => floatval($body['altura'] ?? 0),
            'largura' => floatval($body['largura'] ?? 0),
            'comprimento' => floatval($body['comprimento'] ?? 0),
        ];

        if (!in_array($payload['syncsite'], ['S', 'N'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        foreach (['peso','altura','largura','comprimento'] as $k) {
            if (!is_finite($payload[$k]) || $payload[$k] < 0) $payload[$k] = 0;
        }

        sb('produto', ['codprod' => 'eq.' . $codprod], 'PATCH', $payload, SUPABASE_SERVICE_KEY);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch product details
$select = 'codprod,descrprod,comnome,desccurta,descrprodoed,syncsite,codgrupoprod,peso,altura,largura,comprimento,'
        . 'preco(vlr_venda),estoque(estoque_disponivel),produto_imagem(url,ordem)';

$res = sb('produto', ['codprod' => 'eq.' . $codprod, 'select' => $select], 'GET', null, SUPABASE_SERVICE_KEY);
if (empty($res)) {
    echo "Produto não encontrado.";
    exit;
}
$prod = $res[0];

$admin_page_title = 'Editar Produto - ' . $codprod;
$admin_active     = 'produtos';
include __DIR__ . '/layout.php';

// Extract related data
$price = $prod['preco'][0]['vlr_venda'] ?? 0;
$stock = $prod['estoque'][0]['estoque_disponivel'] ?? 0;
$images = $prod['produto_imagem'] ?? [];
usort($images, fn($a, $b) => ($a['ordem'] ?? 999) - ($b['ordem'] ?? 999));
?>

<style>
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
  .form-group { display: flex; flex-direction: column; gap: 0.25rem; }
  .form-group label { font-size: 0.875rem; font-weight: 500; color: #374151; }
  .form-group input, .form-group textarea, .form-group select {
    border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem;
  }
  .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none; border-color: #facc15; ring: 2px solid #facc15;
  }
  .full-width { grid-column: span 2; }
  .btn-save { background: #000; color: #facc15; font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: none; cursor: pointer; transition: background 0.2s; }
  .btn-save:hover { background: #facc15; color: #000; }
  .image-grid { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem; }
  .img-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden; background: #f9fafb; padding: 0.5rem; text-align: center; }
  .img-card img { max-width: 100px; max-height: 100px; object-fit: contain; }
  .toast { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-weight: 500; display: none; }
  .toast-success { background: #dcfce3; color: #166534; border: 1px solid #bbf7d0; }
  .toast-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<div style="max-width: 800px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e5e7eb;">
  
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: #111827;">Informações do Produto</h2>
    <a href="/admin/produtos.php" style="color: #6b7280; font-size: 0.875rem; text-decoration: none;">&larr; Voltar</a>
  </div>

  <div id="toast" class="toast"></div>

  <form id="edit-form" onsubmit="saveProduct(event)">
    <div class="form-grid">
      <div class="form-group full-width">
        <label for="comnome">Nome Comercial</label>
        <input type="text" id="comnome" name="comnome" value="<?= htmlspecialchars($prod['comnome'] ?? '') ?>" placeholder="Nome amigável para exibição">
      </div>
      
      <div class="form-group full-width">
        <label>Nome Original (Sistema)</label>
        <input type="text" value="<?= htmlspecialchars($prod['descrprod'] ?? '') ?>" disabled style="background:#f3f4f6; cursor:not-allowed;">
      </div>

      <div class="form-group">
        <label for="syncsite">Exibir no Site?</label>
        <select id="syncsite" name="syncsite">
          <option value="S" <?= ($prod['syncsite'] ?? '') === 'S' ? 'selected' : '' ?>>Sim</option>
          <option value="N" <?= ($prod['syncsite'] ?? '') === 'N' ? 'selected' : '' ?>>Não</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Preço de Venda (R$)</label>
        <input type="text" value="<?= number_format($price, 2, ',', '.') ?>" disabled style="background:#f3f4f6; cursor:not-allowed;">
        <span style="font-size:0.75rem; color:#9ca3af;">O preço e o estoque são atualizados no ERP.</span>
      </div>

      <div class="form-group full-width">
        <label for="desccurta">Descrição Curta</label>
        <textarea id="desccurta" name="desccurta" rows="2"><?= htmlspecialchars($prod['desccurta'] ?? '') ?></textarea>
      </div>

      <div class="form-group full-width">
        <label for="descrprodoed">Descrição Completa (HTML)</label>
        <textarea id="descrprodoed" name="descrprodoed" rows="6"><?= htmlspecialchars($prod['descrprodoed'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label for="peso">Peso (g)</label>
        <input type="number" id="peso" name="peso" value="<?= floatval($prod['peso'] ?? 0) ?>" step="0.01">
      </div>

      <div class="form-group">
        <label for="altura">Altura (cm)</label>
        <input type="number" id="altura" name="altura" value="<?= floatval($prod['altura'] ?? 0) ?>" step="0.01">
      </div>

      <div class="form-group">
        <label for="largura">Largura (cm)</label>
        <input type="number" id="largura" name="largura" value="<?= floatval($prod['largura'] ?? 0) ?>" step="0.01">
      </div>

      <div class="form-group">
        <label for="comprimento">Comprimento (cm)</label>
        <input type="number" id="comprimento" name="comprimento" value="<?= floatval($prod['comprimento'] ?? 0) ?>" step="0.01">
      </div>
    </div>

    <div style="margin-top: 2rem;">
      <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.5rem;">
        <label style="font-size: 0.875rem; font-weight: 500; color: #374151;">Imagens (<?= count($images) ?>)</label>
        <button type="button" id="btn-clear-cache" onclick="clearImageCache()"
          style="font-size:0.75rem; padding:0.25rem 0.75rem; border:1px solid #d1d5db; border-radius:0.375rem; background:#fff; cursor:pointer; color:#374151;">
          Atualizar cache de imagens
        </button>
        <span id="cache-clear-status" style="font-size:0.75rem; color:#6b7280;"></span>
      </div>
      <div class="image-grid">
        <?php foreach ($images as $img): ?>
          <div class="img-card">
            <img src="<?= htmlspecialchars($img['url']) ?>" alt="Img">
            <div style="font-size:0.7rem; color:#6b7280; margin-top:0.25rem;">Ordem: <?= $img['ordem'] ?? 'N/A' ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($images)): ?>
          <div style="font-size:0.875rem; color:#9ca3af;">Nenhuma imagem cadastrada.</div>
        <?php endif; ?>
      </div>
    </div>

    <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
      <button type="submit" class="btn-save" id="btn-submit">Salvar Alterações</button>
    </div>
  </form>

</div>

<script>
const CODPROD       = <?= $codprod ?>;

async function clearImageCache() {
  const btn = document.getElementById('btn-clear-cache');
  const status = document.getElementById('cache-clear-status');
  btn.disabled = true;
  btn.innerText = 'Limpando...';
  status.innerText = '';
  try {
    const res = await fetch(`?id=${CODPROD}&action=image-cache-clear`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
    const resp = await res.json().catch(() => null);
    if (res.ok && resp?.ok) {
      status.style.color = '#166534';
      status.innerText = 'Cache limpo. Recarregando...';
      setTimeout(() => window.location.reload(), 800);
    } else {
      status.style.color = '#991b1b';
      status.innerText = 'Erro ao limpar cache.';
    }
  } catch (err) {
    status.style.color = '#991b1b';
    status.innerText = 'Erro de rede.';
  }
  btn.disabled = false;
  btn.innerText = 'Atualizar cache de imagens';
}

async function saveProduct(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-submit');
  const toast = document.getElementById('toast');
  btn.disabled = true;
  btn.innerText = 'Salvando...';
  toast.style.display = 'none';
  toast.className = 'toast';

  const data = {
    comnome: document.getElementById('comnome').value,
    syncsite: document.getElementById('syncsite').value,
    desccurta: document.getElementById('desccurta').value,
    descrprodoed: document.getElementById('descrprodoed').value,
    peso: parseFloat(document.getElementById('peso').value) || 0,
    altura: parseFloat(document.getElementById('altura').value) || 0,
    largura: parseFloat(document.getElementById('largura').value) || 0,
    comprimento: parseFloat(document.getElementById('comprimento').value) || 0
  };

  try {
    const res = await fetch(`?id=${CODPROD}&action=produto-save`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
    const resp = await res.json().catch(() => null);
    if (res.ok && resp?.ok) {
      toast.innerText = 'Produto atualizado com sucesso!';
      toast.classList.add('toast-success');
    } else {
      toast.innerText = 'Erro ao atualizar produto.';
      toast.classList.add('toast-error');
    }
  } catch (err) {
    toast.innerText = 'Erro de rede ao salvar.';
    toast.classList.add('toast-error');
  }
  
  toast.style.display = 'block';
  btn.disabled = false;
  btn.innerText = 'Salvar Alterações';
}
</script>

<?php include __DIR__ . '/layout-end.php'; ?>
