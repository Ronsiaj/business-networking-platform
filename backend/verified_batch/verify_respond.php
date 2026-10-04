<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* -------------------------------------------------
   PREFLIGHT
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* -------------------------------------------------
   DEPENDENCIES
------------------------------------------------- */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH (ADMIN ONLY)
------------------------------------------------- */
require_admin();

$user = $GLOBALS['auth_user'] ?? null;
if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$admin_id = (int)$user['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON input", 400, "INVALID_JSON");
}

$user_id = (int)($input['user_id'] ?? 0);
$action  = strtoupper(trim($input['action'] ?? ''));
$reason  = trim($input['reason'] ?? '');

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if ($user_id <= 0) {
    sendError("user_id is required", 422, "VALIDATION_ERROR");
}

if (!in_array($action, ['APPROVE','REJECT'])) {
    sendError("Invalid action. Use APPROVE or REJECT", 422, "VALIDATION_ERROR");
}

if ($action === 'REJECT' && $reason === '') {
    sendError("Reason is required for rejection", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   CHECK USER EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$user_id]);

if (!$stmt->fetch()) {
    sendError("User not found", 404, "USER_NOT_FOUND");
}

/* -------------------------------------------------
   FETCH PROFILE
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, business_name 
    FROM business_profiles 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);

$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    sendError("Business profile not found", 404, "PROFILE_NOT_FOUND");
}

/* -------------------------------------------------
   TRANSACTION (ADMIN OVERRIDE ENABLED)
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    if ($action === 'APPROVE') {

        /* ✅ APPROVE */
        $stmt = $pdo->prepare("
            UPDATE business_profiles 
            SET verification_status='APPROVED',
                is_verified=1,
                verified_by=?,
                verified_at=NOW(),
                verification_reason=NULL
            WHERE user_id=?
        ");
        $stmt->execute([$admin_id, $user_id]);

        /* ✅ USER LOGIN ENABLE */
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status='ACTIVE'
            WHERE id=?
        ");
        $stmt->execute([$user_id]);

        $msg = "🎉 Your business '{$profile['business_name']}' has been approved and verified";
        $type = "BUSINESS_APPROVED";

    } else {

        /* ❌ REJECT */
        $stmt = $pdo->prepare("
            UPDATE business_profiles 
            SET verification_status='REJECTED',
                verification_reason=?,
                is_verified=0,
                verified_by=?,
                verified_at=NOW()
            WHERE user_id=?
        ");
        $stmt->execute([$reason, $admin_id, $user_id]);

        /* ❌ BLOCK LOGIN */
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status='SUSPENDED'
            WHERE id=?
        ");
        $stmt->execute([$user_id]);

        $msg = "❌ Verification rejected: {$reason}";
        $type = "BUSINESS_REJECTED";
    }

    /* -------------------------------------------------
       🔔 NOTIFICATION
    ------------------------------------------------- */
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $user_id,
        $admin_id,
        $type,
        $profile['id'],
        $msg
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Failed to process request", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Verification {$action}ED successfully", [
    "user_id" => $user_id,
    "status" => $action === 'APPROVE' ? 'APPROVED' : 'REJECTED'
]);