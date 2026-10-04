<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   HEADERS
------------------------------------------------- */

// Allow all origins (restrict in production)
header("Access-Control-Allow-Origin: *");

// Allowed methods
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

$action     = strtolower(trim($input['action'] ?? ''));
$chat_id    = (int)($input['chat_id'] ?? 0);
$message_id = (int)($input['message_id'] ?? 0);

if (!$action) {
    sendError("action is required", 400, "ACTION_REQUIRED");
}

/* =================================================
   1️⃣ CHAT DELETE (USER VIEW ONLY)
================================================= */
if ($action === 'delete_chat') {

    if ($chat_id <= 0) {
        sendError("chat_id is required", 400, "CHAT_ID_REQUIRED");
    }

    $stmt = $pdo->prepare("
        SELECT id, user_one, user_two
        FROM chats
        WHERE id = ?
    ");
    $stmt->execute([$chat_id]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chat) {
        sendError("Chat not found", 404, "CHAT_NOT_FOUND");
    }

    if ((int)$chat['user_one'] === $user_id) {
        $pdo->prepare("
            UPDATE chats SET deleted_by_one = 1
            WHERE id = ?
        ")->execute([$chat_id]);

    } elseif ((int)$chat['user_two'] === $user_id) {
        $pdo->prepare("
            UPDATE chats SET deleted_by_two = 1
            WHERE id = ?
        ")->execute([$chat_id]);

    } else {
        sendError("Access denied", 403, "CHAT_ACCESS_DENIED");
    }

    sendSuccess("Chat deleted for current user", [
        "chat_id" => $chat_id
    ]);
}

/* =================================================
   2️⃣ SINGLE MESSAGE DELETE (SELF VIEW)
================================================= */
if ($action === 'delete_message') {

    if ($message_id <= 0) {
        sendError("message_id is required", 400, "MESSAGE_ID_REQUIRED");
    }

    $stmt = $pdo->prepare("
        SELECT id, sender_id
        FROM chat_messages
        WHERE id = ?
    ");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        sendError("Message not found", 404, "MESSAGE_NOT_FOUND");
    }

    if ((int)$msg['sender_id'] === $user_id) {
        $pdo->prepare("
            UPDATE chat_messages
            SET deleted_by_sender = 1
            WHERE id = ?
        ")->execute([$message_id]);
    } else {
        $pdo->prepare("
            UPDATE chat_messages
            SET deleted_by_receiver = 1
            WHERE id = ?
        ")->execute([$message_id]);
    }

    sendSuccess("Message deleted for you", [
        "message_id" => $message_id
    ]);
}

/* =================================================
   3️⃣ DELETE FOR EVERYONE (BUSINESS ONLY)
================================================= */
if ($action === 'delete_for_everyone') {

    if ($message_id <= 0) {
        sendError("message_id is required", 400, "MESSAGE_ID_REQUIRED");
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM chat_messages
        WHERE id = ?
    ");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        sendError("Message not found", 404, "MESSAGE_NOT_FOUND");
    }

    $pdo->prepare("
        UPDATE chat_messages
        SET deleted_for_everyone = 1
        WHERE id = ?
    ")->execute([$message_id]);

    sendSuccess("Message deleted for everyone", [
        "message_id" => $message_id
    ]);
}

/* -------------------------------------------------
   INVALID ACTION
------------------------------------------------- */
sendError("Invalid action", 400, "INVALID_ACTION");