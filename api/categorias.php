<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$cats = sb('categoria', [
    'select' => 'codgrupoprod,descr_grupo,codgrupopai',
    'order' => 'codgrupoprod.asc',
]);
$cats = filter_hidden_categories($cats, false);

echo json_encode(array_values($cats), JSON_UNESCAPED_UNICODE);
