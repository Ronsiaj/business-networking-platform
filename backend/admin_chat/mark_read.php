<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST, OPTIONS");
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

/*
  Admin  → business_user_id mandatory
  Business → auto self
*/
if ($role === 'admin') {
    $business_user_id = (int)($input['business_user_id'] ?? 0);

    if ($business_user_id <= 0) {
        sendError("business_user_id is required", 400, "BUSINESS_USER_REQUIRED");
    }
} else {
    $business_user_id = $user_id;
}

/* -------------------------------------------------
   VERIFY BUSINESS PROFILE
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id
    FROM business_profiles
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$business_user_id]);

if (!$stmt->fetch()) {
    sendError("Business profile not found", 404, "BUSINESS_NOT_FOUND");
}

/* -------------------------------------------------
   MARK MESSAGES AS READ
   - Admin reads business → mark admin side
   - Business reads admin → mark business side
------------------------------------------------- */
if ($role === 'admin') {

    // Admin reading business messages
    $stmt = $pdo->prepare("
        UPDATE admin_messages
        SET is_read = 1,
            read_at = NOW()
        WHERE business_user_id = ?
          AND admin_id != ?
          AND is_read = 0
    ");
    $stmt->execute([$business_user_id, $user_id]);

} else {

    // Business reading admin messages
    $stmt = $pdo->prepare("
        UPDATE admin_messages
        SET is_read = 1,
            read_at = NOW()
        WHERE business_user_id = ?
          AND admin_id > 0
          AND is_read = 0
    ");
    $stmt->execute([$business_user_id]);
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Admin chat messages marked as read", [
    "business_user_id" => $business_user_id,
    "viewer_role"      => $role
]);
