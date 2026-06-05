<?php
// Cache em camadas: APCu (memória, ~0.05ms) → disco local do projeto (~2ms).
// Loga falhas explicitamente em vez de @silenciar, pra você ver problemas em prod.
//
// Por que NÃO sys_get_temp_dir():
//   - systemd-tmpfiles limpa /tmp periodicamente
//   - PHP-FPM com pools diferentes pode ter TMPDIR distinto
//   - shared hosts às vezes bloqueiam via open_basedir
//   - falhas eram silenciadas com @, ninguém via
//
// Uso:
//   $hit = cache_get('minha_chave', 60);  // null se miss/expirado
//   cache_set('minha_chave', $valor, 60);
//   cache_delete('minha_chave');
//   cache_purge_prefix('api_prods_');     // limpa todos com esse prefixo

function cache_dir(): string
{
    static $resolved = null;
    if ($resolved !== null) return $resolved;

    $candidates = [
        dirname(__DIR__) . '/cache',
        sys_get_temp_dir() . '/spark_cache',
    ];

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            $resolved = $dir;
            return $resolved;
        }
    }

    error_log('[spark cache] FATAL: nenhum diretório de cache gravável. Tentei: ' . implode(', ', $candidates));
    $resolved = '';
    return $resolved;
}

function cache_key_normalize(string $key): string
{
    // Aceita qualquer string e produz nome de arquivo seguro
    if (preg_match('/^[A-Za-z0-9_\-\.]{1,200}$/', $key)) return $key;
    return 'k_' . sha1($key);
}

function cache_get(string $key, int $ttl)
{
    $key = cache_key_normalize($key);

    // 1) APCu (mais rápido)
    if (function_exists('apcu_fetch')) {
        $hit = apcu_fetch('spark_' . $key, $ok);
        if ($ok) return $hit;
    }

    // 2) Disco
    $dir = cache_dir();
    if ($dir === '') return null;

    $file = $dir . '/' . $key . '.cache';
    $stat = @stat($file);
    if (!$stat) return null;
    if ((time() - $stat['mtime']) >= $ttl) return null;

    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return null;

    $data = @unserialize($raw, ['allowed_classes' => false]);
    if ($data === false && $raw !== 'b:0;') return null;

    // Promove pro APCu pra próxima
    if (function_exists('apcu_store')) {
        apcu_store('spark_' . $key, $data, $ttl);
    }
    return $data;
}

function cache_set(string $key, $value, int $ttl): bool
{
    $key = cache_key_normalize($key);

    if (function_exists('apcu_store')) {
        apcu_store('spark_' . $key, $value, $ttl);
    }

    $dir = cache_dir();
    if ($dir === '') return false;

    $file = $dir . '/' . $key . '.cache';
    // Escrita atômica: escreve em .tmp e renomeia. Evita leitura parcial concorrente.
    $tmp  = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';

    $payload = serialize($value);
    $bytes   = @file_put_contents($tmp, $payload, LOCK_EX);
    if ($bytes === false) {
        error_log('[spark cache] falha ao escrever ' . $tmp);
        return false;
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        error_log('[spark cache] falha no rename para ' . $file);
        return false;
    }
    return true;
}

function cache_delete(string $key): void
{
    $key = cache_key_normalize($key);
    if (function_exists('apcu_delete')) {
        @apcu_delete('spark_' . $key);
    }
    $dir = cache_dir();
    if ($dir === '') return;
    @unlink($dir . '/' . $key . '.cache');
}

function cache_purge_prefix(string $prefix): int
{
    $count = 0;
    if (function_exists('apcu_iterator') && class_exists('APCUIterator')) {
        try {
            $it = new APCUIterator('/^spark_' . preg_quote($prefix, '/') . '/');
            foreach ($it as $entry) {
                if (@apcu_delete($entry['key'])) $count++;
            }
        } catch (\Throwable $e) {
            error_log('[spark cache] purge APCu erro: ' . $e->getMessage());
        }
    }
    $dir = cache_dir();
    if ($dir !== '') {
        foreach (glob($dir . '/' . $prefix . '*.cache') ?: [] as $file) {
            if (@unlink($file)) $count++;
        }
    }
    return $count;
}
