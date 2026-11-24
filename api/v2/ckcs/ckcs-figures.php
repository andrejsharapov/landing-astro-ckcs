<?php

$configPath = __DIR__ . '/../../../../api/v1/ckcs-services-config.php';

if (!file_exists($configPath)) {
    error_log('API ckcs-figures: config.php not found');
    http_response_code(500);
    echo json_encode(["error" => "Server configuration error"], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $stmt = $pdo->query(
        "SELECT slug, name, size, price, price_prefix, tag, color, image_url, description 
         FROM ckcs_figures 
         WHERE visible = 1 
         ORDER BY sort_order ASC, id ASC"
    );

    $figures = $stmt->fetchAll();

    echo json_encode([
        "ok" => true,
        "items" => $figures
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('API ckcs-figures DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Ошибка получения каталога"], JSON_UNESCAPED_UNICODE);
}
