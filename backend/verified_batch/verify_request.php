<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* PREFLIGHT */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* DEPENDENCIES */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* METHOD */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

/* AUTH */
require_business();

$user = $GLOBALS['auth_user'] ?? null;
if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$user_id = (int)$user['id'];

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON input", 400, "INVALID_JSON");
}

$gst  = strtoupper(trim($input['gst_number'] ?? ''));
$lic  = trim($input['license_number'] ?? '');

/* VALIDATION */
if ($gst === '' || $lic === '') {
    sendError("GST and License required", 422, "VALIDATION_ERROR");
}

/* GST VALIDATION */
if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gst)) {
    sendError("Invalid GST format", 422, "INVALID_GST");
}

if (strlen($lic) < 5) {
    sendError("Invalid license number", 422, "INVALID_LICENSE");
}

/* USER CHECK */
$stmt = $pdo->prepare("SELECT id FROM users WHERE id=?");
$stmt->execute([$user_id]);

if (!$stmt->fetch()) {
    sendError("User not found", 404, "USER_NOT_FOUND");
}

/* PROFILE */
$stmt = $pdo->prepare("
    SELECT id,business_name,is_verified,verification_status 
    FROM business_profiles 
    WHERE user_id=?
");
$stmt->execute([$user_id]);

$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    sendError("Profile not found", 404, "PROFILE_NOT_FOUND");
}

/* ALREADY VERIFIED */
if ((int)$p['is_verified'] === 1) {
    sendError("Already verified", 409, "ALREADY_VERIFIED");
}

/* BLOCK ONLY IF PENDING */
if ($p['verification_status'] === 'PENDING') {
    sendError("Already requested. Wait for admin", 409, "ALREADY_PENDING");
}

/* DUPLICATE GST */
$stmt = $pdo->prepare("
    SELECT id FROM business_profiles 
    WHERE gst_number=? AND user_id!=?
");
$stmt->execute([$gst,$user_id]);

if ($stmt->fetch()) {
    sendError("GST already used", 409, "DUPLICATE_GST");
}

/* TRANSACTION */
try {

    $pdo->beginTransaction();

    /* UPDATE */
    $stmt = $pdo->prepare("
        UPDATE business_profiles 
        SET gst_number=?, 
            license_number=?, 
            verification_status='PENDING',
            is_verified=0,
            verification_reason=NULL
        WHERE user_id=?
    ");

    $stmt->execute([$gst,$lic,$user_id]);

    /* 🔔 NOTIFY ADMINS */
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id,actor_user_id,type,reference_id,message)
        SELECT id, ?, 'BUSINESS_VERIFY_REQUEST', ?, ?
        FROM users WHERE role='admin'
    ");

    $stmt->execute([
        $user_id,
        $p['id'],
        "{$p['business_name']} requested verification"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Failed to submit request", 500, "SERVER_ERROR");
}

/* RESPONSE */
sendSuccess("Verification request submitted", [
    "status" => "PENDING"
]);