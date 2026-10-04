<?php
require_once '../auth/middleware.php';
require_once '../helpers/db.php';
require_once '../helpers/response.php';

/* -------------------------------------------------
   HEADERS
------------------------------------------------- */

// Allow frontend access (change in production)
header("Access-Control-Allow-Origin: *");

// Allowed HTTP methods
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// Allowed headers
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Response type
header("Content-Type: application/json");

/* -------------------------------------------------
   AUTH
------------------------------------------------- */
require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$data = json_decode(file_get_contents("php://input"), true);
$chat_id = (int)($data['chat_id'] ?? 0);

if ($chat_id <= 0) {
    sendError("chat_id is required", 400, "CHAT_ID_REQUIRED");
}

/* -------------------------------------------------
   VERIFY ACCESS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM chats
    WHERE id = ?
      AND (user_one = ? OR user_two = ?)
");
$stmt->execute([$chat_id, $user_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Access denied", 403, "CHAT_ACCESS_DENIED");
}

/* -------------------------------------------------
   MARK MESSAGES AS READ
------------------------------------------------- */
$stmt = $pdo->prepare("
    UPDATE chat_messages
    SET is_read = 1,
        read_at = NOW()
    WHERE chat_id = ?
      AND sender_id != ?
      AND is_read = 0
");
$stmt->execute([$chat_id, $user_id]);

sendSuccess("Messages marked as read");