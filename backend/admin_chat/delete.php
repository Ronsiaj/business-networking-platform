<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* -------------------------------------------------
   PREFLIGHT
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   AUTH : ADMIN OR BUSINESS
------------------------------------------------- */
require_auth();
$authUser = $GLOBALS['auth_user'];

$user_id = (int)$authUser['id'];
$role    = $authUser['role'];

if (!in_array($role, ['admin', 'business'])) {
    sendError("Access denied", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

$action     = strtolower(trim($input['action'] ?? ''));
$message_id = (int)($input['message_id'] ?? 0);
$business_user_id = (int)($input['business_user_id'] ?? 0);

if (!$action) {
    sendError("action is required", 400, "ACTION_REQUIRED");
}

/* =================================================
   1️⃣ CHAT DELETE (USER VIEW ONLY)
================================================= */
if ($action === 'delete_chat') {

    if ($business_user_id <= 0) {
        sendError("business_user_id is required", 400, "BUSINESS_USER_REQUIRED");
    }

    if ($role === 'admin') {
        $pdo->prepare("
            UPDATE admin_messages
            SET deleted_by_admin = 1
            WHERE business_user_id = ?
        ")->execute([$business_user_id]);
    } else {
        $pdo->prepare("
            UPDATE admin_messages
            SET deleted_by_business = 1
            WHERE business_user_id = ?
        ")->execute([$user_id]);
    }

    sendSuccess("Chat deleted for current user");
}

/* =================================================
   2️⃣ SINGLE MESSAGE DELETE (SELF VIEW)
================================================= */

if ($action === 'delete_message') {

    if ($message_id <= 0) {
        sendError("message_id is required", 400, "MESSAGE_ID_REQUIRED");
    }

    if ($role === 'admin') {
        $pdo->prepare("
            UPDATE admin_messages
            SET deleted_by_admin = 1
            WHERE id = ?
        ")->execute([$message_id]);
    } else {
        $pdo->prepare("
            UPDATE admin_messages
            SET deleted_by_business = 1
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

    if ($role !== 'business') {
        sendError("Only business can delete for everyone", 403, "FORBIDDEN");
    }

    if ($message_id <= 0) {
        sendError("message_id is required", 400, "MESSAGE_ID_REQUIRED");
    }

    $pdo->prepare("
        UPDATE admin_messages
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
