<?php

// Список разрешенных источников.
$allowedOrigins = [
    'http://localhost:5173', // Vite dev server
    'http://localhost:4321', // Astro dev server
    'http://localhost:8081', // dev
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Строгая проверка по списку.
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

// Обработка preflight-запроса.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Разбор пути.
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Убираем префикс /api/v2/.
$basePath = '/api/v2/';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

$parts = explode('/', trim($path, '/'));
$resource = $parts[0] ?? '';
$slug = $parts[1] ?? '';

// Роутинг.
if ($resource === 'services' && $slug) {
    require __DIR__ . '/service.php';
} elseif ($resource === 'ckcs-leads' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/ckcs/ckcs-leads.php';
} elseif ($resource === 'ckcs-figures' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/ckcs/ckcs-figures.php';
} else {
    http_response_code(404);
    echo json_encode(["error" => "Not found"], JSON_UNESCAPED_UNICODE);
}