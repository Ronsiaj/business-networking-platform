<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* HANDLE PREFLIGHT REQUEST */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   AUTH
------------------------------------------------- */

require_auth();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */

$data = json_decode(file_get_contents("php://input"), true);
$notification_id = (int)($data['notification_id'] ?? 0);

if ($notification_id <= 0) {
    sendError("notification_id is required", 400, "NOTIFICATION_ID_REQUIRED");
}

/* -------------------------------------------------
   VERIFY OWNERSHIP
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id
    FROM notifications
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");
$stmt->execute([$notification_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Notification not found", 404, "NOTIFICATION_NOT_FOUND");
}

/* -------------------------------------------------
   MARK AS READ
------------------------------------------------- */
$pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = ?
")->execute([$notification_id]);

sendSuccess("Notification marked as read", [
    "notification_id" => $notification_id
]);
