<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* -------------------------------------------------
   PREFLIGHT
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$authUser = $GLOBALS['auth_user'];
$my_user_id = (int)$authUser['id'];

/* -------------------------------------------------
   INPUT (QUERY PARAMS)
------------------------------------------------- */
$chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($chat_id <= 0) {
    sendError("chat_id is required", 400, "CHAT_ID_REQUIRED");
}

if ($target_user_id <= 0) {
    sendError("user_id is required", 400, "USER_ID_REQUIRED");
}

/* -------------------------------------------------
   VERIFY CHAT ACCESS (BOTH USERS)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT user_one, user_two
    FROM chats
    WHERE id = ?
      AND (user_one = ? OR user_two = ?)
    LIMIT 1
");
$stmt->execute([$chat_id, $my_user_id, $my_user_id]);
$chat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chat) {
    sendError("Access denied", 403, "CHAT_ACCESS_DENIED");
}

/* -------------------------------------------------
   ENSURE TARGET USER IS PART OF CHAT
------------------------------------------------- */
if (
    $target_user_id !== (int)$chat['user_one'] &&
    $target_user_id !== (int)$chat['user_two']
) {
    sendError("Invalid user for this chat", 403, "INVALID_CHAT_USER");
}

/* -------------------------------------------------
   FETCH LAST SEEN
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT last_seen
    FROM user_last_seen
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$target_user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   CALCULATE ONLINE STATUS
------------------------------------------------- */
$online = false;
$last_seen = null;

if ($row && $row['last_seen']) {
    $last_seen_time = strtotime($row['last_seen']);
    $online = (time() - $last_seen_time) < 60;
    $last_seen = $row['last_seen'];
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Last seen fetched successfully",
    [
        "user_id"   => $target_user_id,
        "online"    => $online,
        "last_seen" => $online ? null : $last_seen
    ]
);
