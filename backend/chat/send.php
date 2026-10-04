<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/notification.php';

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

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
$message = strtolower(trim($input['message'] ?? ''));
$message_type = strtoupper($input['message_type'] ?? 'TEXT');

if ($chat_id <= 0) {
    sendError("chat_id is required", 400, "CHAT_ID_REQUIRED");
}

if ($message === '') {
    sendError("message is required", 400, "MESSAGE_REQUIRED");
}

/* -------------------------------------------------
   VERIFY CHAT ACCESS + FETCH USERS  ✅ FIX
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, user_one, user_two
    FROM chats
    WHERE id = ?
      AND (user_one = ? OR user_two = ?)
    LIMIT 1
");
$stmt->execute([$chat_id, $user_id, $user_id]);
$chat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chat) {
    sendError("Access denied", 403, "CHAT_ACCESS_DENIED");
}

/* -------------------------------------------------
   FETCH BUSINESS PROFILE (RESPONSE)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, business_name
    FROM business_profiles
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$businessProfile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$businessProfile) {
    sendError("Business profile not found", 404, "BUSINESS_PROFILE_NOT_FOUND");
}

/* -------------------------------------------------
   IDENTIFY RECEIVER  ✅ FIX
------------------------------------------------- */
$receiver_id =
    ((int)$chat['user_one'] === $user_id)
        ? (int)$chat['user_two']
        : (int)$chat['user_one'];

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    /* INSERT MESSAGE */
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages
            (chat_id, sender_id, message, message_type, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $chat_id,
        $user_id,
        $message,
        $message_type
    ]);

    $message_id = (int)$pdo->lastInsertId();

    /* -------------------------------------------------
       INSERT NOTIFICATION  ✅ FIX
    ------------------------------------------------- */
    createNotification(
        $pdo,
        $receiver_id,          // user_id (receiver)
        $user_id,              // actor_user_id
        'chat_message',        // type
        $chat_id,              // reference_id
        'sent you a message'   // message
    );

    /* UPDATE CHAT META */
    $stmt = $pdo->prepare("
        UPDATE chat_activity_log
        SET message_count = message_count + 1,
            last_message_at = NOW()
        WHERE chat_id = ?
    ");
    $stmt->execute([$chat_id]);

    $pdo->commit();

    sendSuccess(
        "Message sent successfully",
        [
            "chat_id"    => $chat_id,
            "message_id" => $message_id,
            "message"    => $message,
            "type"       => $message_type,
            "sender"     => [
                "business_profile_id" => (int)$businessProfile['id'],
                "business_name"       => $businessProfile['business_name']
            ]
        ]
    );

} catch (Throwable $e) {
    $pdo->rollBack();

    sendError(
        "Failed to send message",
        500,
        "SERVER_ERROR"
    );
}
