<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

header("Content-Type: application/json");
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
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$user_id = $GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   CHECK PROFILE EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, status
    FROM business_profiles
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    sendError(
        "Business profile not found",
        404,
        "PROFILE_NOT_FOUND"
    );
}

/* -------------------------------------------------
   ALREADY INACTIVE CHECK
------------------------------------------------- */
if ($profile['status'] === 'INACTIVE') {
    sendError(
        "Business profile already inactive",
        409,
        "PROFILE_ALREADY_INACTIVE"
    );
}

/* -------------------------------------------------
   SOFT DELETE
------------------------------------------------- */
$stmt = $pdo->prepare("
    UPDATE business_profiles
    SET status = 'INACTIVE',
        updated_at = NOW()
    WHERE user_id = ?
");
$stmt->execute([$user_id]);

sendSuccess("Business profile deleted successfully");
