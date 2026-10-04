<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed", 405);
}

require_auth();

$user = $GLOBALS['auth_user'] ?? null;
if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$user_id = (int)$user['id'];

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) sendError("Invalid JSON", 400);

$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$meeting_date = $input['meeting_date'] ?? '';
$reminder_days = (int)($input['reminder_days_before'] ?? 1);

/* VALIDATION */
if ($title === '') sendError("Title required", 422);

if (!$meeting_date || strtotime($meeting_date) === false) {
    sendError("Invalid meeting_date", 422);
}

if (strtotime($meeting_date) <= time()) {
    sendError("Meeting must be future date", 422);
}

if ($reminder_days < 0 || $reminder_days > 7) {
    sendError("Reminder days must be between 0-7", 422);
}

/* INSERT */
$stmt = $pdo->prepare("
INSERT INTO user_meetings
(user_id, title, description, meeting_date, reminder_days_before)
VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $user_id,
    $title,
    $description ?: null,
    $meeting_date,
    $reminder_days
]);

$meeting_id = (int)$pdo->lastInsertId();

/* 🔔 NOTIFICATION (optional immediate confirm) */
$stmt = $pdo->prepare("
INSERT INTO notifications (user_id, actor_user_id, type, reference_id, message)
VALUES (?, ?, 'MEETING_CREATED', ?, ?)
");

$stmt->execute([
    $user_id,
    $user_id,
    $meeting_id,
    "Meeting '{$title}' scheduled successfully"
]);

sendSuccess("Meeting created", [
    "meeting_id" => $meeting_id
]);