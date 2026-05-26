<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];

$email = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Credenciais inválidas'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = local_db_load();
$authUsers = $db['auth_users'] ?? [];

$match = null;
foreach ($authUsers as $u) {
    if (strtolower((string)($u['email'] ?? '')) === $email) {
        $match = $u;
        break;
    }
}

if (!$match || empty($match['password_hash']) || !password_verify($password, (string)$match['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'E-mail ou senha incorretos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = (string)($match['id'] ?? '');
$cliente = null;
foreach (($db['cliente'] ?? []) as $c) {
    if ((string)($c['id'] ?? '') === $uid) {
        $cliente = $c;
        break;
    }
}

if (!$cliente) {
    http_response_code(500);
    echo json_encode(['error' => 'Usuário local sem cliente vinculado'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'user' => [
        'id' => $cliente['id'],
        'nome' => $cliente['nome'] ?? '',
        'email' => $cliente['email'] ?? $email,
        'telefone' => $cliente['telefone'] ?? '',
        'cpf_cnpj' => $cliente['cpf_cnpj'] ?? '',
        'is_admin' => (bool)($cliente['is_admin'] ?? false),
    ]
], JSON_UNESCAPED_UNICODE);

