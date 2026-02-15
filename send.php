<?php

$token = '7701289673:AAF_PlwfWafkXZAdWW4qtRvHYq6rrY8kU80';
$chatId = '607299479';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload) {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  respond(405, ['ok' => false, 'error' => 'Method Not Allowed. Use POST.']);
}

// Разрешаем только "обычную форму"
$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if ($contentType === '' || (strpos($contentType, 'application/x-www-form-urlencoded') === false
    && strpos($contentType, 'multipart/form-data') === false)) {
  // Если прилетел JSON или что-то другое — режем
  respond(400, [
    'ok' => false,
    'error' => 'Only HTML form POST is allowed (x-www-form-urlencoded or multipart/form-data).'
  ]);
}

// Читаем ТОЛЬКО $_POST
$firstName = trim((string)($_POST['firstName'] ?? ''));
$lastName  = trim((string)($_POST['lastName']  ?? ''));
$email     = trim((string)($_POST['email']     ?? ''));
$phone     = trim((string)($_POST['phone']     ?? ''));

// Валидация
$errors = [];

if ($firstName === '') $errors['firstName'] = 'Required';
if ($lastName === '')  $errors['lastName']  = 'Required';
if ($email === '')     $errors['email']     = 'Required';
if ($phone === '')     $errors['phone']     = 'Required';

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors['email'] = 'Invalid email format';
}

// Небольшая “адекватность” телефона (не супер-строго)
if ($phone !== '' && !preg_match('/^[0-9+\-\s().]{6,30}$/', $phone)) {
  $errors['phone'] = 'Invalid phone format';
}

if ($errors) {
  respond(400, [
    'ok' => false,
    'error' => 'Validation error',
    'fields' => $errors
  ]);
}

if ($token === '' || $chatId === '') {
  respond(500, [
    'ok' => false,
    'error' => 'Server is not configured: TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID is missing.'
  ]);
}

// Сообщение
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$time = date('Y-m-d H:i:s');

$text =
  "📝 Новая заявка\n"
  . "Имя: {$firstName}\n"
  . "Фамилия: {$lastName}\n"
  . "Email: {$email}\n"
  . "Телефон: {$phone}\n"
  . "—\n"
  . "IP: {$ip}\n"
  . "UA: {$ua}\n"
  . "Время: {$time}";
// Отправка в Telegram (SSL полностью отключен)
$url = "https://api.telegram.org/bot{$token}/sendMessage";

$postData = [
    'chat_id' => $chatId,
    'text'    => $text
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,

    // ВОТ ЭТО ГЛАВНОЕ
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false) {
    respond(502, [
        'ok' => false,
        'error' => 'Telegram request failed',
        'details' => $error
    ]);
}

$decoded = json_decode($response, true);

if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
    respond(502, [
        'ok' => false,
        'error' => 'Telegram API error',
        'http_code' => $httpCode,
        'response' => $decoded ?: $response
    ]);
}

respond(200, [
    'ok' => true,
    'message' => 'Sent to Telegram'
]);
