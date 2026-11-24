<?php

/**
 * Отправка уведомления о новой заявке в Telegram через Bot API
 */
function sendTelegramLead(array $config, array $lead): void
{
    $token  = $config['tg_token'] ?? '';
    $chatId = $config['tg_chat_id'] ?? '';

    if ($token === '' || $chatId === '') {
        return;
    }

    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $text  = "🐶 <b>Новая заявка на ККЧС!</b>\n\n";
    $text .= "<b>Фигурка:</b> " . $esc($lead['figure']) . "\n";
    $text .= "<b>Имя:</b> " . $esc($lead['name']) . "\n";
    $text .= "<b>Контакт:</b> " . $esc($lead['contact']) . "\n";

    if ($lead['comment'] !== '') {
        $text .= "<b>Комментарий:</b> " . $esc($lead['comment']) . "\n";
    }

    $text .= "\n<i>IP: " . $esc((string)($lead['ip'] ?? 'не определён')) . "</i>";

    $ch = curl_init("https://telegram.org{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_POSTFIELDS     => [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Telegram API error ({$httpCode}): " . (string)$response);
    }
}

$configPath = __DIR__ . '/../../../api/v1/config.php';

if (!file_exists($configPath)) {
    error_log('API ckcs-leads: config.php not found');
    http_response_code(500);
    echo json_encode(["error" => "Server configuration error"], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Проверка Honeypot: если скрытое поле заполнено — это бот
$honeypot = trim((string)($data['website'] ?? ''));
if ($honeypot !== '') {
    echo json_encode([
        "ok" => true,
        "message" => "Спасибо! Заявка принята, скоро напишем вам."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Проверка времени заполнения (не быстрее 2 секунд с момента открытия страницы)
$loadedAt = (int)($data['loaded_at'] ?? 0);
$nowMs = (int)round(microtime(true) * 1000);
if ($loadedAt > 0 && ($nowMs - $loadedAt) < 2000) {
    http_response_code(429);
    echo json_encode(["error" => "Слишком быстрая отправка. Попробуйте ещё раз."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Валидация и обрезка длины полей
$name    = mb_substr(trim((string)($data['name'] ?? '')), 0, 100);
$contact = mb_substr(trim((string)($data['contact'] ?? '')), 0, 150);
$figure  = mb_substr(trim((string)($data['figure'] ?? 'Не выбрана')), 0, 150);
$comment = mb_substr(trim((string)($data['comment'] ?? '')), 0, 1000);

$ip = $_SERVER['HTTP_X_REAL_IP']
    ?? (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]) : null)
    ?? ($_SERVER['REMOTE_ADDR'] ?? null);

if ($name === '' || $contact === '') {
    http_response_code(400);
    echo json_encode(["error" => "Укажите имя и контакт для связи"], JSON_UNESCAPED_UNICODE);
    exit;
}

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

    // 4. Rate-limiting по IP: не более 3 заявок за последние 10 минут
    if ($ip !== null) {
        $rateStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM ckcs_leads WHERE ip = ? AND created_at >= (NOW() - INTERVAL 10 MINUTE)"
        );
        $rateStmt->execute([$ip]);
        $recentCount = (int)$rateStmt->fetchColumn();

        if ($recentCount >= 3) {
            http_response_code(429);
            echo json_encode([
                "error" => "Вы отправили слишком много заявок. Попробуйте снова через 10 минут."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 5. Сохранение заявки в БД
    $stmt = $pdo->prepare(
        "INSERT INTO ckcs_leads (name, contact, figure, comment, ip, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$name, $contact, $figure, $comment, $ip]);

    // 6. Уведомление в Telegram
    sendTelegramLead($config, [
        'name'    => $name,
        'contact' => $contact,
        'figure'  => $figure,
        'comment' => $comment,
        'ip'      => $ip,
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "Спасибо, {$name}! Заявка принята, скоро напишем вам."
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('API ckcs-leads DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Ошибка сохранения заявки"], JSON_UNESCAPED_UNICODE);
}
