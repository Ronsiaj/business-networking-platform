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
   AUTH (ANY LOGGED USER)
------------------------------------------------- */
require_auth();
$authUser = $GLOBALS['auth_user'];

/* -------------------------------------------------
   TARGET USER ID (OWN OR OTHER)
------------------------------------------------- */
$target_user_id = isset($_GET['user_id']) 
    ? (int)$_GET['user_id'] 
    : (int)$authUser['id'];

/* -------------------------------------------------
   FETCH USER DETAILS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        id,
        role,
        name,
        email,
        mobile,
        status,
        email_verified,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$target_user_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    sendError("User not found", 404, "USER_NOT_FOUND");
}

/* -------------------------------------------------
   FETCH BUSINESS PROFILE (IF EXISTS)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        id, user_id, business_name, logo, cover_image, short_bio,
        category_id, sub_category_id, city, area, phone, whatsapp,
        website, profile_completion, status, created_at, updated_at,
        verification_status,
        is_verified,
        gst_number,
        license_number,
        verified_at,
        verification_reason
    FROM business_profiles
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$target_user_id]);
$businessProfile = $stmt->fetch(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("User profile fetched successfully", [
    "is_own_profile" => ($target_user_id === (int)$authUser['id']),
    "user" => [
        "id"             => (int)$userData['id'],
        "role"           => $userData['role'],
        "name"           => $userData['name'],
        "email"          => $userData['email'],
        "mobile"         => $userData['mobile'],
        "status"         => $userData['status'],
        "email_verified" => (bool)$userData['email_verified'],
        "created_at"     => $userData['created_at']
    ],
    "business_profile" => $businessProfile ?: null
]);