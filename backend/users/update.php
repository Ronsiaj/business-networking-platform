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
   INPUT
------------------------------------------------- */
$data = $_POST;

/* -------------------------------------------------
   CHECK PROFILE EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, logo, cover_image, phone 
    FROM business_profiles 
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    sendError("Business profile not found", 404, "PROFILE_NOT_FOUND");
}

/* -------------------------------------------------
   PHONE VALIDATION + DUPLICATE CHECK (IF PROVIDED)
------------------------------------------------- */
if (!empty($data['phone'])) {

    if (!preg_match('/^[6-9]\d{9}$/', $data['phone'])) {
        sendError("Invalid phone number", 400, "INVALID_PHONE");
    }

    $stmt = $pdo->prepare("
        SELECT id FROM business_profiles
        WHERE phone = ? AND user_id != ?
        LIMIT 1
    ");
    $stmt->execute([$data['phone'], $user_id]);

    if ($stmt->fetch()) {
        sendError("Phone number already used", 409, "DUPLICATE_PHONE");
    }
}

/* -------------------------------------------------
   LOGO UPLOAD (OPTIONAL)
------------------------------------------------- */
$logoPath = $profile['logo'];

if (!empty($_FILES['logo']['name'])) {

    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','webp'];

    if (!in_array($ext, $allowedExt)) {
        sendError("Invalid logo format", 400, "INVALID_LOGO_FORMAT");
    }

    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
        sendError("Logo size must be below 2MB", 400, "LOGO_TOO_LARGE");
    }

    $dir = __DIR__ . '/../uploads/logos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = uniqid('logo_') . '.' . $ext;
    $logoPath = 'uploads/logos/' . $name;

    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $name)) {
        sendError("Logo upload failed", 500, "UPLOAD_FAILED");
    }
}

/* -------------------------------------------------
   COVER IMAGE UPLOAD (OPTIONAL)
------------------------------------------------- */
$coverImagePath = $profile['cover_image'];

if (!empty($_FILES['cover_image']['name'])) {

    $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','webp'];

    if (!in_array($ext, $allowedExt)) {
        sendError("Invalid cover image format", 400, "INVALID_COVER_FORMAT");
    }

    if ($_FILES['cover_image']['size'] > 3 * 1024 * 1024) {
        sendError("Cover image size must be below 3MB", 400, "COVER_TOO_LARGE");
    }

    $dir = __DIR__ . '/../uploads/covers/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = uniqid('cover_') . '.' . $ext;
    $coverImagePath = 'uploads/covers/' . $name;

    if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . $name)) {
        sendError("Cover image upload failed", 500, "UPLOAD_FAILED");
    }
}

/* -------------------------------------------------
   WEBSITE VALIDATION (OPTIONAL)
------------------------------------------------- */
$website = null;
if (!empty($data['website'])) {
    if (!filter_var($data['website'], FILTER_VALIDATE_URL)) {
        sendError("Invalid website URL", 400, "INVALID_WEBSITE");
    }
    $website = strtolower(trim($data['website']));
}

/* -------------------------------------------------
   UPDATE PROFILE
------------------------------------------------- */
$stmt = $pdo->prepare("
    UPDATE business_profiles SET
        business_name   = COALESCE(?, business_name),
        short_bio       = COALESCE(?, short_bio),
        category_id     = COALESCE(?, category_id),
        sub_category_id = COALESCE(?, sub_category_id),
        city            = COALESCE(?, city),
        area            = COALESCE(?, area),
        phone           = COALESCE(?, phone),
        whatsapp        = COALESCE(?, whatsapp),
        website         = COALESCE(?, website),
        logo            = ?,
        cover_image     = ?,
        updated_at      = NOW()
    WHERE user_id = ?
");

$stmt->execute([
    !empty($data['business_name']) ? strtolower(trim($data['business_name'])) : null,
    !empty($data['short_bio']) ? strtolower(trim($data['short_bio'])) : null,
    $data['category_id'] ?? null,
    $data['sub_category_id'] ?? null,
    !empty($data['city']) ? strtolower(trim($data['city'])) : null,
    !empty($data['area']) ? strtolower(trim($data['area'])) : null,
    $data['phone'] ?? null,
    $data['phone'] ?? null, // whatsapp same as phone
    $website,
    $logoPath,
    $coverImagePath,
    $user_id
]);

sendSuccess("Business profile updated successfully");
