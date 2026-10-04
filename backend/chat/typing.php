<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   HEADERS
------------------------------------------------- */

// Allow all origins (restrict in production)
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
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);
$chat_id = (int)($input['chat_id'] ?? 0);

if ($chat_id <= 0) {
    sendError("chat_id is required", 400, "CHAT_ID_REQUIRED");
}

/* -------------------------------------------------
   VERIFY CHAT ACCESS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id
    FROM chats
    WHERE id = ?
      AND (user_one = ? OR user_two = ?)
");
$stmt->execute([$chat_id, $user_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Access denied", 403, "CHAT_ACCESS_DENIED");
}

/* -------------------------------------------------
   UPSERT TYPING STATE
------------------------------------------------- */
$stmt = $pdo->prepare("
    INSERT INTO chat_typing (chat_id, user_id, updated_at)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE updated_at = NOW()
");
$stmt->execute([$chat_id, $user_id]);

sendSuccess("Typing status updated");