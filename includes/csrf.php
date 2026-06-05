<?php
// csrf.php — Session-scoped CSRF token helpers.
//
// Usage:
//   require_once __DIR__ . '/includes/csrf.php';
//   csrf_session_start();
//   $token = csrf_token();
//
// Validation in a POST handler:
//   if (!csrf_validate_request()) {
//       http_response_code(403);
//       echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
//       exit;
//   }
//
// The token rotates per session. JSON endpoints should expect it in the
// 'X-CSRF-Token' header; HTML forms should embed it via csrf_field().

if (!function_exists('csrf_session_start')) {
    function csrf_session_start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (headers_sent()) return;
        $savePath = (string)session_save_path();
        @ini_set('session.use_strict_mode', '1');
        $candidates = [];
        if ($savePath !== '' && !str_contains(strtolower($savePath), 'xampp')) $candidates[] = $savePath;
        $candidates[] = sys_get_temp_dir();
        $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sessions';
        $chosen = '';
        foreach ($candidates as $p) {
            $p = trim((string)$p);
            if ($p === '') continue;
            $check = $p;
            if (str_contains($check, ';')) {
                $parts = explode(';', $check);
                $check = trim((string)end($parts));
            }
            if ($check === '') continue;
            if (!is_dir($check)) continue;
            if (!is_writable($check)) continue;
            $chosen = $check;
            break;
        }
        $effective = $savePath;
        if (str_contains($effective, ';')) {
            $parts = explode(';', $effective);
            $effective = trim((string)end($parts));
        }
        $effective_ok = ($effective !== '' && !str_contains(strtolower($effective), 'xampp') && is_dir($effective) && is_writable($effective));
        if (!$effective_ok && $chosen !== '') {
            session_save_path($chosen);
        }
        if ($chosen === '') {
            $bases = [
                rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'spark-sessions',
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sessions',
            ];
            foreach ($bases as $base) {
                if (!is_dir($base)) @mkdir($base, 0777, true);
                if (is_dir($base) && is_writable($base)) {
                    session_save_path($base);
                    break;
                }
            }
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        csrf_session_start();
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $t . '">';
    }
}

if (!function_exists('csrf_validate_request')) {
    function csrf_validate_request(): bool {
        csrf_session_start();
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!is_string($expected) || $expected === '') return false;

        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (is_string($header) && $header !== '' && hash_equals($expected, $header)) return true;

        $posted = $_POST['csrf_token'] ?? '';
        if (is_string($posted) && $posted !== '' && hash_equals($expected, $posted)) return true;

        return false;
    }
}
