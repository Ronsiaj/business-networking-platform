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
   COUNT UNREAD
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");
$stmt->execute([$user_id]);
$count = (int)$stmt->fetchColumn();

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Unread notification count", [
    "unread_count" => $count
]);
