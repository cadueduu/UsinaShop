<?php
if (!function_exists('spark_load_env_file')) {
    function spark_load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) return;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $key = trim(substr($line, 0, $pos));
            if ($key === '') continue;
            $val = trim(substr($line, $pos + 1));
            if ($val !== '' && (($val[0] === '"' && str_ends_with($val, '"')) || ($val[0] === "'" && str_ends_with($val, "'")))) {
                $val = substr($val, 1, -1);
            }
            $existing = getenv($key);
            if ($existing !== false && $existing !== '') continue;
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

$spark_env_file = getenv('SPARK_ENV_FILE');
if ($spark_env_file !== false && $spark_env_file !== '') {
    spark_load_env_file($spark_env_file);
} else {
    spark_load_env_file(dirname(__DIR__) . '/.env');
}
// ─── Supabase Configuration ───────────────────────────────────────────────────
$sb_url_env = getenv('SUPABASE_URL');
if ($sb_url_env === false || $sb_url_env === '') $sb_url_env = getenv('NEXT_PUBLIC_SUPABASE_URL');
define('SUPABASE_URL', ($sb_url_env !== false && $sb_url_env !== '') ? $sb_url_env : '');

$sb_anon_env = getenv('SUPABASE_ANON_KEY');
if ($sb_anon_env === false || $sb_anon_env === '') $sb_anon_env = getenv('NEXT_PUBLIC_SUPABASE_ANON_KEY');
define('SUPABASE_ANON_KEY', ($sb_anon_env !== false && $sb_anon_env !== '') ? $sb_anon_env : '');

$svc_key_env = getenv('SUPABASE_SERVICE_KEY');
if ($svc_key_env === false || $svc_key_env === '') $svc_key_env = getenv('SUPABASE_SERVICE_ROLE_KEY');
define('SUPABASE_SERVICE_KEY', ($svc_key_env !== false && $svc_key_env !== '') ? $svc_key_env : '');

$sb_bucket_env = getenv('SUPABASE_BUCKET');
define('SUPABASE_BUCKET', ($sb_bucket_env !== false && $sb_bucket_env !== '') ? $sb_bucket_env : 'product-assets');

// ─── App Configuration ────────────────────────────────────────────────────────
define('SITE_NAME',    'Spark Eletrônica');
define('SITE_LOGO',    '/assets/images/produtos/logo.png');
define('CEP_ORIGEM', (getenv('CEP_ORIGEM') !== false && getenv('CEP_ORIGEM') !== '') ? getenv('CEP_ORIGEM') : '38025430');
$cws_token_env = getenv('CORREIOS_CWS_TOKEN');
define('CORREIOS_CWS_TOKEN', ($cws_token_env !== false && $cws_token_env !== '') ? $cws_token_env : '');
$cws_user_env = getenv('CORREIOS_CWS_USER');
define('CORREIOS_CWS_USER', ($cws_user_env !== false && $cws_user_env !== '') ? $cws_user_env : '');
$cws_contrato_env = getenv('CORREIOS_CONTRATO');
define('CORREIOS_CONTRATO', ($cws_contrato_env !== false && $cws_contrato_env !== '') ? $cws_contrato_env : '');
$cws_dr_env = getenv('CORREIOS_DR');
define('CORREIOS_DR', ($cws_dr_env !== false && $cws_dr_env !== '') ? $cws_dr_env : '');
$cws_cartao_env = getenv('CORREIOS_CARTAO');
define('CORREIOS_CARTAO', ($cws_cartao_env !== false && $cws_cartao_env !== '') ? $cws_cartao_env : '');
$cws_unidade_env = getenv('CORREIOS_UNIDADE');
define('CORREIOS_UNIDADE', ($cws_unidade_env !== false && $cws_unidade_env !== '') ? $cws_unidade_env : '');
$cws_jwt_env = getenv('CORREIOS_CWS_JWT');
define('CORREIOS_CWS_JWT', ($cws_jwt_env !== false && $cws_jwt_env !== '') ? $cws_jwt_env : '');

$mp_access_token_env = getenv('MP_ACCESS_TOKEN');
define('MP_ACCESS_TOKEN', ($mp_access_token_env !== false && $mp_access_token_env !== '') ? $mp_access_token_env : '');

$mp_runtime_env = getenv('MP_RUNTIME_ENV');
$mp_runtime_env = ($mp_runtime_env !== false && $mp_runtime_env !== '') ? strtoupper(trim((string)$mp_runtime_env)) : '';
define('MP_RUNTIME_ENV', in_array($mp_runtime_env, ['LOCAL', 'SERVER'], true) ? $mp_runtime_env : 'SERVER');

$mp_webhook_secret_env = getenv('MP_WEBHOOK_SECRET');
define('MP_WEBHOOK_SECRET', ($mp_webhook_secret_env !== false && $mp_webhook_secret_env !== '') ? $mp_webhook_secret_env : '');

$app_base_url_env = getenv('APP_BASE_URL');
define('APP_BASE_URL', ($app_base_url_env !== false && $app_base_url_env !== '') ? rtrim((string)$app_base_url_env, '/') : '');
// Define como false por padrão — conecta ao Supabase real.
// Para rodar localmente sem banco: LOCAL_DATA_MODE=true php -S localhost:8080
$local_mode_env = getenv('LOCAL_DATA_MODE');
define('LOCAL_DATA_MODE', $local_mode_env !== false && in_array(strtolower($local_mode_env), ['1', 'true', 'yes'], true));

// Guard: LOCAL_DATA_MODE must never be active alongside production credentials.
// If it is, all sb() calls silently route to the local JSON file instead of
// Supabase, breaking the live store without any visible error.
if (LOCAL_DATA_MODE && (MP_ACCESS_TOKEN !== '' || SUPABASE_SERVICE_KEY !== '')) {
    error_log(
        '[SPARK CRITICAL] LOCAL_DATA_MODE=true is set while production credentials ' .
            '(MP_ACCESS_TOKEN or SUPABASE_SERVICE_KEY) are present. ' .
            'All database reads/writes are routing to the local JSON file. ' .
            'Payment processing is blocked, but the live store is broken. ' .
            'Unset LOCAL_DATA_MODE in your environment immediately.'
    );
}

// ─── Local DB Helpers (JSON) ──────────────────────────────────────────────────
function local_db_file()
{
    return dirname(__DIR__) . '/data/local-db.json';
}

function local_db_load()
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $file = local_db_file();
    $defaults = ['categoria' => [], 'produto' => [], 'embalagem' => [], 'preco' => [], 'estoque' => [], 'produto_imagem' => [], 'especificacao' => [], 'cliente' => [], 'endereco' => [], 'pedido' => [], 'pedido_item' => []];
    if (!file_exists($file)) {
        $cache = $defaults;
        return $cache;
    }
    $json = file_get_contents($file);
    $db = json_decode($json, true);
    $cache = is_array($db) ? array_merge($defaults, $db) : $defaults;
    return $cache;
}

function local_db_save($db)
{
    $file = local_db_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function local_match_value($row, $field, $rule)
{
    $raw = urldecode((string)$rule);
    $val = isset($row[$field]) ? (string)$row[$field] : '';

    if (str_starts_with($raw, 'eq.')) {
        return $val === substr($raw, 3);
    }
    if (str_starts_with($raw, 'in.(') && str_ends_with($raw, ')')) {
        $inside = substr($raw, 4, -1);
        $parts = array_map('trim', explode(',', $inside));
        return in_array($val, $parts, true);
    }
    if (str_starts_with($raw, 'ilike.')) {
        $pattern = substr($raw, 6);
        $needle = trim($pattern, '*');
        return $needle === '' ? true : str_contains(mb_strtolower($val), mb_strtolower($needle));
    }
    return $val === $raw;
}

function local_filter_rows($rows, $params)
{
    foreach ($params as $k => $v) {
        if (in_array($k, ['select', 'order', 'limit', 'offset'], true)) {
            continue;
        }
        $rows = array_values(array_filter($rows, fn($r) => local_match_value($r, $k, $v)));
    }
    return $rows;
}

function local_sort_rows($rows, $order)
{
    if (empty($order)) return $rows;
    $parts = explode('.', $order);
    $field = $parts[0] ?? '';
    $dir = strtolower($parts[1] ?? 'asc');
    usort($rows, function ($a, $b) use ($field, $dir) {
        $av = $a[$field] ?? null;
        $bv = $b[$field] ?? null;
        if ($av == $bv) return 0;
        $cmp = ($av < $bv) ? -1 : 1;
        return $dir === 'desc' ? -$cmp : $cmp;
    });
    return $rows;
}

function local_attach_produto_relations($prod, $db)
{
    $id = $prod['codprod'] ?? 0;
    $prod['preco'] = array_values(array_filter($db['preco'] ?? [], fn($r) => ($r['codprod'] ?? 0) == $id));
    $prod['estoque'] = array_values(array_filter($db['estoque'] ?? [], fn($r) => ($r['codprod'] ?? 0) == $id));
    $imgs = array_values(array_filter($db['produto_imagem'] ?? [], fn($r) => ($r['codprod'] ?? 0) == $id));
    usort($imgs, fn($a, $b) => ($a['ordem'] ?? 999) <=> ($b['ordem'] ?? 999));
    $prod['produto_imagem'] = $imgs;
    $prod['especificacao'] = array_values(array_filter($db['especificacao'] ?? [], fn($r) => ($r['codprod'] ?? 0) == $id));
    return $prod;
}

function sb_local($endpoint, $params = [], $method = 'GET', $body = null)
{
    $table = trim($endpoint, '/');
    $db = local_db_load();
    if (!isset($db[$table])) return [];

    $method = strtoupper($method);
    if ($method === 'GET') {
        $rows = $db[$table];
        $rows = local_filter_rows($rows, $params);
        if (!empty($params['order'])) {
            $rows = local_sort_rows($rows, $params['order']);
        }
        $offset = intval($params['offset'] ?? 0);
        if ($offset > 0) {
            $rows = array_slice($rows, $offset);
        }
        if (isset($params['limit'])) {
            $rows = array_slice($rows, 0, intval($params['limit']));
        }
        if ($table === 'produto') {
            $rows = array_map(fn($p) => local_attach_produto_relations($p, $db), $rows);
        }
        if ($table === 'pedido') {
            $rows = array_map(function ($pedido) use ($db) {
                $pid = $pedido['id'] ?? 0;
                $pedido['pedido_item'] = array_values(array_filter($db['pedido_item'] ?? [], fn($r) => ($r['pedido_id'] ?? 0) == $pid));
                return $pedido;
            }, $rows);
        }
        return array_values($rows);
    }

    if ($method === 'PATCH') {
        $rows = $db[$table];
        $updated = [];
        foreach ($rows as $i => $row) {
            $ok = true;
            foreach ($params as $k => $v) {
                if (in_array($k, ['select', 'order', 'limit', 'offset'], true)) continue;
                if (!local_match_value($row, $k, $v)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $rows[$i] = array_merge($row, $body ?? []);
                $updated[] = $rows[$i];
            }
        }
        $db[$table] = $rows;
        local_db_save($db);
        return $updated;
    }

    if ($method === 'POST') {
        $payload = $body;
        if (isset($payload[0]) && is_array($payload[0])) {
            foreach ($payload as $item) $db[$table][] = $item;
        } else {
            $db[$table][] = $payload;
        }
        local_db_save($db);
        return $payload;
    }

    if ($method === 'DELETE') {
        $rows = $db[$table];
        $kept = [];
        foreach ($rows as $row) {
            $match = true;
            foreach ($params as $k => $v) {
                if (in_array($k, ['select', 'order', 'limit', 'offset'], true)) continue;
                if (!local_match_value($row, $k, $v)) {
                    $match = false;
                    break;
                }
            }
            if (!$match) $kept[] = $row;
        }
        $db[$table] = $kept;
        local_db_save($db);
        return [];
    }

    return [];
}

// ─── Helper: Supabase REST API Call ──────────────────────────────────────────
function sb($endpoint, $params = [], $method = 'GET', $body = null, $token = null, $extra_headers = [])
{
    if (LOCAL_DATA_MODE) {
        return sb_local($endpoint, $params, $method, $body);
    }
    if (SUPABASE_URL === '') {
        error_log('[Supabase] SUPABASE_URL não configurado.');
        return [];
    }
    $bearer = $token ?? (SUPABASE_ANON_KEY !== '' ? SUPABASE_ANON_KEY : null);
    $apiKey = SUPABASE_ANON_KEY !== '' ? SUPABASE_ANON_KEY : ($token ?? '');
    if ($bearer === null || $bearer === '' || $apiKey === '') {
        error_log('[Supabase] Chaves não configuradas (SUPABASE_ANON_KEY ou token).');
        return [];
    }

    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    if (!empty($params)) {
        $parts = [];
        foreach ($params as $k => $v) {
            // select and order values contain special chars (parens, commas) that PostgREST expects unencoded
            if (in_array($k, ['select', 'order'], true)) {
                $parts[] = rawurlencode($k) . '=' . $v;
            } else {
                $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
            }
        }
        $url .= '?' . implode('&', $parts);
    }
    $headers = array_merge([
        'apikey: '        . $apiKey,
        'Authorization: Bearer ' . $bearer,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ], $extra_headers);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp     = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        error_log("[Supabase cURL] Erro: $curlErr | URL: $url");
        return [];
    }

    $decoded = json_decode($resp, true);

    // PostgREST retorna objeto com "code"+"message" em caso de erro
    if (is_array($decoded) && isset($decoded['code'], $decoded['message']) && !isset($decoded[0])) {
        error_log("[Supabase $httpCode] {$decoded['code']}: {$decoded['message']} | URL: $url");
        return [];
    }

    return $decoded ?? [];
}

// ─── Helper: Parallel Supabase REST calls via curl_multi ─────────────────────
// $queries: assoc array of [ key => [$endpoint, $params, $method, $body, $token, $extra_headers] ]
// Returns: assoc array [ key => result_array ]
function sb_multi(array $queries): array
{
    if (LOCAL_DATA_MODE) {
        $results = [];
        foreach ($queries as $key => $q) {
            $q = array_pad((array)$q, 6, null);
            $results[$key] = sb_local((string)$q[0], (array)($q[1] ?? []), (string)($q[2] ?? 'GET'), $q[3]);
        }
        return $results;
    }
    if (SUPABASE_URL === '' || SUPABASE_ANON_KEY === '') {
        return array_fill_keys(array_keys($queries), []);
    }

    $mh = curl_multi_init();
    $handles = [];
    foreach ($queries as $key => $q) {
        $q             = array_pad((array)$q, 6, null);
        $endpoint      = (string)$q[0];
        $params        = (array)($q[1] ?? []);
        $method        = strtoupper((string)($q[2] ?? 'GET'));
        $body          = $q[3];
        $token         = $q[4];
        $extra_headers = (array)($q[5] ?? []);
        $bearer        = $token ?? SUPABASE_ANON_KEY;
        $apiKey        = SUPABASE_ANON_KEY;

        $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
        if (!empty($params)) {
            $parts = [];
            foreach ($params as $k => $v) {
                if (in_array($k, ['select', 'order'], true)) {
                    $parts[] = rawurlencode($k) . '=' . $v;
                } else {
                    $parts[] = rawurlencode($k) . '=' . rawurlencode((string)$v);
                }
            }
            $url .= '?' . implode('&', $parts);
        }

        $headers = array_merge([
            'apikey: '        . $apiKey,
            'Authorization: Bearer ' . $bearer,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ], $extra_headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $key => $ch) {
        $resp = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            error_log("[Supabase multi $code] key=$key");
            $results[$key] = [];
            continue;
        }
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded) && isset($decoded['code'], $decoded['message']) && !isset($decoded[0])) {
            error_log("[Supabase multi] {$decoded['code']}: {$decoded['message']} key=$key");
            $results[$key] = [];
            continue;
        }
        $results[$key] = $decoded ?? [];
    }
    curl_multi_close($mh);
    return $results;
}

function admin_require_basic_auth(): void
{
    $user = getenv('ADMIN_USER');
    $pass = getenv('ADMIN_PASS');
    $user = ($user !== false) ? (string)$user : '';
    $pass = ($pass !== false) ? (string)$pass : '';

    if ($user === '' || $pass === '') {
        http_response_code(503);
        echo 'Admin não configurado.';
        exit;
    }

    $inUser = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
    $inPass = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
    if (!hash_equals($user, $inUser) || !hash_equals($pass, $inPass)) {
        header('WWW-Authenticate: Basic realm="Admin"');
        http_response_code(401);
        echo 'Auth requerida.';
        exit;
    }
}

// ─── Helper: Format product display name ─────────────────────────────────────
function prod_name($prod)
{
    $n = (!empty($prod['comnome'])) ? $prod['comnome'] : $prod['descrprod'];
    return $n ?? '';
}

// ─── Helper: Format category name ────────────────────────────────────────────
function cat_name($name)
{
    if (empty($name)) return '';
    return mb_strtoupper(mb_substr($name, 0, 1)) . mb_strtolower(mb_substr($name, 1));
}

function cat_key($name)
{
    $name = mb_strtoupper(trim((string)$name));
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($converted !== false) $name = $converted;
    }
    $name = preg_replace('/[^A-Z0-9]+/u', '', $name);
    return $name;
}

function hidden_category_keys(): array
{
    static $keys = null;
    if ($keys !== null) return $keys;
    $names = [
        'Intermediario PI',
        'JLW',
        'Maquina de Solda',
        'Adesivo',
        'Chaveiro',
        'Kit e-commerce',
        'Vestuario',
    ];
    $keys = array_map('cat_key', $names);
    return $keys;
}

function filter_hidden_categories(array $cats, bool $include_hidden): array
{
    if ($include_hidden) return $cats;
    $hidden = array_flip(hidden_category_keys());
    return array_values(array_filter($cats, function ($c) use ($hidden) {
        $k = cat_key($c['descr_grupo'] ?? '');
        return !isset($hidden[$k]);
    }));
}

// ─── Helper: Format currency ─────────────────────────────────────────────────
function fmt_brl($value)
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

// ─── Helper: Strict low-resolution URL validator ─────────────────────────────
// Defense-in-depth check at the render boundary:
//   - Local site paths (/assets/...): pass through (no low/high semantics).
//   - Remote URLs: path must contain a 'low' whole-word AND no 'high' whole-word.
// Word boundaries: / _ - . and start/end of path segment.
function is_low_resolution_image_url(string $url): bool
{
    if ($url === '') return false;
    if ($url[0] === '/') return true;

    $path = parse_url($url, PHP_URL_PATH) ?? '';
    if ($path === '') return false;
    if (preg_match('/(^|[\/_.\-])high([\/_.\-]|$)/i', $path)) return false;
    return (bool) preg_match('/(^|[\/_.\-])low([\/_.\-]|$)/i', $path);
}

// ─── Helper: Get product image URL (strict low-only) ─────────────────────────
// Drops any image entry whose URL is not a verified low-resolution asset; the
// first remaining entry (by ordem) is returned. If nothing qualifies, the
// shared fallback logo is returned so the UI never renders a high-res variant.
function prod_img($images)
{
    if (empty($images)) return '/assets/images/produtos/logo.png';
    usort($images, fn($a, $b) => ($a['ordem'] ?? 999) - ($b['ordem'] ?? 999));
    foreach ($images as $img) {
        // New shape: reject any path that explicitly marks itself as high-res.
        // The authoritative low/high signal is ext_product_images.resolution_type,
        // already enforced server-side by db_products_images_low_bulk(); the path
        // check here is defense-in-depth against a mis-labeled row, NOT a second
        // gate that requires the path to carry a `_low_` token (legacy uploads
        // often don't, and rejecting them silently hides valid products).
        if (!empty($img['path'])) {
            $p = (string)$img['path'];
            if (preg_match('/(^|[\/_.\-])high([\/_.\-]|$)/i', $p)) continue;
            $url = product_image_render_url($img, 400, 60, 'webp');
            if ($url !== '') return $url;
            continue;
        }
        // Legacy / LOCAL_DATA_MODE: full URL stored.
        $url = (string)($img['url'] ?? '');
        if ($url === '' || !is_low_resolution_image_url($url)) continue;
        return $url;
    }
    return '/assets/images/produtos/logo.png';
}

function supabase_storage_public_url(string $bucket, string $path): string
{
    $bucket = trim($bucket, '/');
    $path = ltrim($path, '/');
    if (SUPABASE_URL === '' || $bucket === '' || $path === '') return '';
    return SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));
}

// ─── Helper: Supabase Image Transformation URL ───────────────────────────────
// Build a URL that asks Supabase to resize / re-encode the image on the edge
// and serve it from its CDN. No PHP I/O, no local proxy, no disk cache — every
// (path, width, quality, format) tuple is cached at the CDN layer.
//
// URL shape:
//   {SUPABASE_URL}/storage/v1/render/image/public/{SUPABASE_BUCKET}/{path}
//     ?width={width}&quality={quality}&format={format}
//
// Example:
//   https://abc.supabase.co/storage/v1/render/image/public/product-assets/
//     1234/foto_low_0.jpg?width=400&quality=60&format=webp
//
// Notes:
// - `render/image` (not `object`) is the transcoding endpoint.
// - `format=webp` typically saves 25–35% vs JPEG at quality=60 with no visible loss;
//   pass `format=origin` to skip re-encoding and keep the source bytes.
// - Aspect ratio is preserved when only `width` is supplied.
// - Requires the Image Transformations add-on (Supabase Pro plan and above).
function supabase_image_url(
    string $imagePath,
    int    $width   = 400,
    int    $quality = 60,
    string $format  = 'webp'
): string {
    if ($imagePath === '' || SUPABASE_URL === '' || SUPABASE_BUCKET === '') return '';

    // Encode each segment so spaces/accents survive, but preserve '/' as a separator.
    $clean   = ltrim($imagePath, '/');
    $encoded = implode('/', array_map('rawurlencode', explode('/', $clean)));

    $query = http_build_query([
        'width'   => $width,
        'quality' => $quality,
        'format'  => $format,
    ]);

    return SUPABASE_URL
        . '/storage/v1/render/image/public/'
        . rawurlencode(SUPABASE_BUCKET) . '/'
        . $encoded
        . '?' . $query;
}

// Extract the bucket-relative object path from a Supabase Storage public URL.
// Strips the `{SUPABASE_URL}/storage/v1/object/public/{SUPABASE_BUCKET}/` prefix
// and any trailing `?…` query string. Returns '' when the URL doesn't belong to
// our configured bucket — caller should then treat the URL as opaque/legacy.
function supabase_path_from_public_url(string $url): string
{
    if ($url === '' || SUPABASE_URL === '' || SUPABASE_BUCKET === '') return '';
    $prefix = SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode(SUPABASE_BUCKET) . '/';
    if (!str_starts_with($url, $prefix)) return '';
    $tail = substr($url, strlen($prefix));
    $q = strpos($tail, '?');
    if ($q !== false) $tail = substr($tail, 0, $q);
    return rawurldecode($tail);
}

// Render an image entry into a final URL.
// New shape: ['path' => 'bucket-relative path', 'version' => 'cache-buster', 'ordem' => N]
//   → built via supabase_image_url() with the Image Transformation params.
// Legacy shape: ['url' => 'full URL']  → returned as-is (LOCAL_DATA_MODE or foreign URLs).
function product_image_render_url(
    array  $img,
    int    $width   = 400,
    int    $quality = 60,
    string $format  = 'webp'
): string {
    if (!empty($img['path'])) {
        $u = supabase_image_url((string)$img['path'], $width, $quality, $format);
        $v = (string)($img['version'] ?? '');
        if ($u !== '' && $v !== '') $u .= '&v=' . rawurlencode($v);
        return $u;
    }
    return (string)($img['url'] ?? '');
}

// ─── Helper: Bulk-fetch low-res image paths from ext_product_images ──────────
// Direct PostgREST query against Supabase — one round-trip for the entire page.
// Replaces the previous chain of:
//   (a) Spark API HTTP calls (`repo_*`),
//   (b) Supabase Storage object listings (`product_storage_*`),
//   (c) on-disk PHP cache (`spark_img_v…`),
// none of which scale beyond ~24 cards before saturating PHP-FPM workers.
//
// Equivalent SQL (PostgREST translates the params below into this query):
//   SELECT product_code, file_path, position, updated_at, created_at
//     FROM ext_product_images
//    WHERE product_code IN (...)
//      AND resolution_type = 'low'
//      AND deleted_at IS NULL
//    ORDER BY product_code, position;
//
// Service role bearer: the table has RLS that hides rows from anon callers;
// the storefront is server-rendered so the key never leaves the PHP process.
function db_products_images_low_bulk(array $productCodes): array
{
    if (LOCAL_DATA_MODE || SUPABASE_URL === '') return [];

    $ids = array_values(array_unique(array_map(fn($v) => (string)intval($v), $productCodes)));
    $ids = array_values(array_filter($ids, fn($v) => $v !== '0'));
    if (empty($ids)) return [];

    $bearer = SUPABASE_SERVICE_KEY !== '' ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    if ($bearer === '') return array_fill_keys($ids, []);

    $rows = sb('ext_product_images', [
        'select'          => 'product_code,file_path,position,updated_at,created_at',
        'product_code'    => 'in.(' . implode(',', $ids) . ')',
        'resolution_type' => 'eq.low',
        'deleted_at'      => 'is.null',
        'order'           => 'product_code.asc,position.asc',
    ], 'GET', null, $bearer);

    $out = array_fill_keys($ids, []);
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $code = (string)($row['product_code'] ?? '');
        if ($code === '' || !array_key_exists($code, $out)) continue;
        $path = trim((string)($row['file_path'] ?? ''));
        if ($path === '') continue;
        // Defense in depth: the resolution_type filter should already exclude
        // 'high' rows, but enforce the same word-boundary regex used by the
        // render-time validator so a mis-labeled row can never leak through.
        if (preg_match('/(^|[\/_.\-])high([\/_.\-]|$)/i', $path)) continue;
        $version = (string)($row['updated_at'] ?? ($row['created_at'] ?? ''));
        $out[$code][] = [
            'path'    => $path,
            'version' => $version,
            'ordem'   => intval($row['position'] ?? 0),
        ];
    }
    return $out;
}

// Single-product convenience wrapper used by checkout endpoints.
function product_images_low($codprod): array
{
    $pid = (string)intval($codprod);
    if ($pid === '0') return [];
    $map = db_products_images_low_bulk([$pid]);
    return $map[$pid] ?? [];
}

// ─── Helper: Enrich products with price, stock, images (and optionally specs) ─
//
// $listing_mode = true  → skips the specs query (product grids never render them)
//                         AND filters unavailable products BEFORE the image fetch,
//                         so images are only fetched for products that will be shown.
//                         Returns an already-filtered list; callers must NOT call
//                         filter_available() again.
// $listing_mode = false → full enrich including specs (used by product detail page).
function enrich_products(array $products, bool $listing_mode = false): array
{
    if (empty($products)) return [];

    $ids       = array_column($products, 'codprod');
    $in_clause = 'in.(' . implode(',', $ids) . ')';

    $batch_queries = [
        'preco'   => ['preco',   ['codprod' => $in_clause, 'select' => 'codprod,vlr_venda']],
        'estoque' => ['estoque', ['codprod' => $in_clause, 'select' => 'codprod,estoque_disponivel']],
    ];
    if (!$listing_mode) {
        $batch_queries['especs'] = ['especificacao', ['codprod' => $in_clause, 'select' => 'codprod,label,valor']];
    }

    $batch  = sb_multi($batch_queries);
    $precos = $batch['preco']   ?? [];
    $estoq  = $batch['estoque'] ?? [];
    $especs = $batch['especs']  ?? [];

    $preco_map   = [];
    foreach ($precos as $r) {
        $preco_map[$r['codprod']][]   = $r;
    }
    $estoque_map = [];
    foreach ($estoq  as $r) {
        $estoque_map[$r['codprod']][] = $r;
    }
    $spec_map    = [];
    foreach ($especs as $r) {
        $spec_map[$r['codprod']][]    = $r;
    }

    // Attach price, stock, and (in full mode) specs to every product.
    // produto_imagem: in production we clear it here and fill from the bulk
    // ext_product_images fetch below. In LOCAL_DATA_MODE the field is already
    // attached by local_attach_produto_relations() from local-db.json, so
    // leave it alone — the bulk fetch is skipped and there is nothing to
    // replace it with.
    foreach ($products as &$prod) {
        $id = $prod['codprod'];
        $prod['preco']          = $preco_map[$id]   ?? [];
        $prod['estoque']        = $estoque_map[$id] ?? [];
        $prod['especificacao']  = $listing_mode ? [] : ($spec_map[$id] ?? []);
        if (!LOCAL_DATA_MODE) {
            $prod['produto_imagem'] = [];
        }
    }
    unset($prod);

    // In listing mode, drop unavailable products NOW — before the expensive image
    // fetch — so we never waste API calls or cache reads on products not shown.
    if ($listing_mode) {
        $products = filter_available($products);
        if (empty($products)) return [];
        $ids = array_column($products, 'codprod');
    }

    // Fetch images for the surviving product set: one PostgREST round-trip against
    // ext_product_images, server-side filtered to resolution_type='low'.
    $imagem_map = LOCAL_DATA_MODE ? [] : db_products_images_low_bulk($ids);

    foreach ($products as &$prod) {
        $id  = $prod['codprod'];
        $pid = (string)intval($id);
        // LOCAL_DATA_MODE keeps whatever shape local-db.json provides
        // (legacy `{url, ordem}`); everything else uses the path-based shape
        // built by db_products_images_low_bulk().
        $prod['produto_imagem'] = LOCAL_DATA_MODE
            ? ($prod['produto_imagem'] ?? [])
            : ($imagem_map[$pid] ?? []);
    }
    unset($prod);

    // Suppress products with no verified low-resolution imagery from grids and listings.
    // Mirror prod_img()'s strict "low-only" gate so a product only survives if at
    // least one image would actually render. Without this, products with neutral
    // path naming (no _low_ token) leak into listings and display the fallback logo.
    if ($listing_mode) {
        $products = array_values(array_filter(
            $products,
            fn($p) => LOCAL_DATA_MODE || prod_img($p['produto_imagem'] ?? []) !== '/assets/images/produtos/logo.png'
        ));
    }

    return $products;
}

// ─── Helper: Filter products with valid price and stock ───────────────────────
function filter_available(array $products): array
{
    return array_values(array_filter($products, function ($p) {
        $price = $p['preco'][0]['vlr_venda']           ?? 0;
        $stock = $p['estoque'][0]['estoque_disponivel'] ?? 0;
        return $price > 0 && $stock > 0;
    }));
}

// ─── Pagination defaults ──────────────────────────────────────────────────────
if (!defined('PROD_PAGE_SIZE'))   define('PROD_PAGE_SIZE',   24);
if (!defined('PROD_FETCH_BATCH')) define('PROD_FETCH_BATCH', 72);

// ─── Helper: Fetch products with price, stock, images ────────────────────────
function fetch_products($extra_params = [], $limit = 20)
{
    $params = array_merge([
        'select' => 'codprod,descrprod,comnome,desccurta,codgrupoprod',
        'limit'  => $limit,
    ], $extra_params);
    $products = sb('produto', $params);
    if (empty($products)) return [];
    return enrich_products($products, true); // listing_mode: no specs, filter before images
}

// ─── Helper: Fetch all categories organized ──────────────────────────────────
function fetch_categories(bool $include_hidden = true)
{
    static $mem = [];
    $cache_key = $include_hidden ? 'all' : 'visible';
    if (isset($mem[$cache_key])) return $mem[$cache_key];

    // File cache: 5-minute TTL (avoids re-fetching on every page request)
    if (!LOCAL_DATA_MODE) {
        $cache_file = sys_get_temp_dir() . '/spark_cats_' . $cache_key . '.json';
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 300) {
            $cached = json_decode(file_get_contents($cache_file), true);
            if (is_array($cached) && !empty($cached)) {
                $mem[$cache_key] = $cached;
                return $cached;
            }
        }
    }

    $cats = sb('categoria', [
        'select' => 'codgrupoprod,descr_grupo,codgrupopai',
        'order'  => 'codgrupoprod.asc',
    ]);
    $cats = filter_hidden_categories($cats, $include_hidden);
    $indexed = [];
    foreach ($cats as $c) {
        $indexed[$c['codgrupoprod']] = $c;
    }
    $roots = [3000000, 4000000];
    $level1 = [];
    foreach ($indexed as $id => $c) {
        if (in_array($c['codgrupopai'], $roots)) {
            $level1[$id] = array_merge($c, ['children' => []]);
        }
    }
    foreach ($indexed as $id => $c) {
        if (!in_array($c['codgrupopai'], $roots) && isset($level1[$c['codgrupopai']])) {
            $level1[$c['codgrupopai']]['children'][$id] = $c;
        }
    }

    if (!LOCAL_DATA_MODE && isset($cache_file)) {
        @file_put_contents($cache_file, json_encode($level1));
    }

    $mem[$cache_key] = $level1;
    return $level1;
}
