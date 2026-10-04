<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];


/* -------------------------------------------------
   FETCH UNREAD COUNT PER CHAT
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        c.id AS chat_id,
        COUNT(cm.id) AS unread_count
    FROM chats c
    JOIN chat_messages cm ON cm.chat_id = c.id
    WHERE (c.user_one = ? OR c.user_two = ?)
      AND cm.sender_id != ?
      AND cm.is_read = 0
    GROUP BY c.id
");
$stmt->execute([$user_id, $user_id, $user_id]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   MAP RESULT
------------------------------------------------- */
$result = [];
foreach ($rows as $r) {
    $result[] = [
        "chat_id"      => (int)$r['chat_id'],
        "unread_count" => (int)$r['unread_count']
    ];
}

sendSuccess("Unread count fetched", $result);
