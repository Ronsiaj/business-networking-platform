<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once '../helpers/notification.php';

require_auth();
$authUser = $GLOBALS['auth_user'];
$user_id  = (int)$authUser['id'];
$role     = $authUser['role'];

if (!in_array($role, ['admin', 'business'])) {
    sendError("Access denied", 403, "FORBIDDEN");
}

$input   = json_decode(file_get_contents("php://input"), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    sendError("message is required", 400, "MESSAGE_REQUIRED");
}

/* Determine business_user_id */
if ($role === 'admin') {
    $business_user_id = (int)($input['business_user_id'] ?? 0);
    if ($business_user_id <= 0) {
        sendError("business_user_id is required", 400, "BUSINESS_USER_REQUIRED");
    }
    $admin_id    = $user_id;
    $sender_role = 'admin';
} else {
    $business_user_id = $user_id;
    $admin_id         = 1; // system admin
    $sender_role      = 'business';
}

/* Verify business profile exists */
$bpStmt = $pdo->prepare("SELECT id, business_name FROM business_profiles WHERE user_id = ? LIMIT 1");
$bpStmt->execute([$business_user_id]);
$businessProfile = $bpStmt->fetch(PDO::FETCH_ASSOC);

if (!$businessProfile) {
    sendError("Business profile not found", 404, "BUSINESS_PROFILE_NOT_FOUND");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO admin_messages (admin_id, business_user_id, sender_role, message, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$admin_id, $business_user_id, $sender_role, $message]);
    $message_id = (int)$pdo->lastInsertId();

    $pdo->commit();

    /* Notify the other party */
    $notify_user = ($role === 'admin') ? $business_user_id : $admin_id;
    createNotification($pdo, $notify_user, $user_id, 'admin_message', null, 'Admin sent you a message');

    sendSuccess("Message sent successfully", [
        "message_id"  => $message_id,
        "message"     => $message,
        "sender_role" => $sender_role,
        "is_me"       => true,
        "created_at"  => date('Y-m-d H:i:s'),
        "sender"      => [
            "business_profile_id" => (int)$businessProfile['id'],
            "business_name"       => $businessProfile['business_name']
        ]
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    sendError("Failed to send message", 500, "SERVER_ERROR");
}