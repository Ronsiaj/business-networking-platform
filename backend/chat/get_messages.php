<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* PREFLIGHT */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* DEPENDENCIES */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* METHOD */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* AUTH */
require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* INPUT */
$chat_id = (int)($_GET['chat_id'] ?? 0);

if ($chat_id <= 0) {
    sendError("chat_id is required", 400, "CHAT_ID_REQUIRED");
}

/* VERIFY CHAT ACCESS */
$stmt = $pdo->prepare("
    SELECT id FROM chats
    WHERE id = ?
      AND (user_one = ? OR user_two = ?)
    LIMIT 1
");
$stmt->execute([$chat_id, $user_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Access denied", 403, "CHAT_ACCESS_DENIED");
}

/* FETCH MESSAGES */
$stmt = $pdo->prepare("
    SELECT
        cm.id,
        cm.chat_id,
        cm.sender_id,
        cm.message,
        cm.message_type,
        cm.file_url,
        cm.file_name,
        cm.file_size,
        cm.is_read,
        cm.read_at,
        cm.deleted_by_sender,
        cm.deleted_by_receiver,
        cm.deleted_for_everyone,
        cm.created_at,
        bp.business_name AS sender_name,
        bp.logo          AS sender_logo
    FROM chat_messages cm
    LEFT JOIN business_profiles bp ON bp.user_id = cm.sender_id
    WHERE cm.chat_id = ?
      AND cm.deleted_for_everyone = 0
      AND (cm.sender_id = ? AND cm.deleted_by_sender = 0
           OR cm.sender_id != ? AND cm.deleted_by_receiver = 0)
    ORDER BY cm.created_at ASC
");
$stmt->execute([$chat_id, $user_id, $user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

sendSuccess("Messages fetched successfully", [
    "chat_id"  => $chat_id,
    "count"    => count($messages),
    "messages" => $messages
]);