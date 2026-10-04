<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';


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
   INPUT (multipart/form-data)
------------------------------------------------- */
$data = $_POST;

/* -------------------------------------------------
   REQUIRED FIELD CHECKS (SEPARATE ERRORS)
------------------------------------------------- */
if (empty($data['business_name'])) sendError("business_name is required", 400, "BUSINESS_NAME_REQUIRED");
if (empty($data['short_bio']))     sendError("short_bio is required", 400, "SHORT_BIO_REQUIRED");
if (empty($data['category_id']))   sendError("category_id is required", 400, "CATEGORY_REQUIRED");
if (empty($data['sub_category_id'])) sendError("sub_category_id is required", 400, "SUB_CATEGORY_REQUIRED");
if (empty($data['city']))          sendError("city is required", 400, "CITY_REQUIRED");
if (empty($data['area']))          sendError("area is required", 400, "AREA_REQUIRED");
if (empty($data['phone']))         sendError("phone is required", 400, "PHONE_REQUIRED");

/* -------------------------------------------------
   VALIDATIONS (SEPARATE)
------------------------------------------------- */
if (!preg_match('/^[6-9]\d{9}$/', $data['phone'])) {
    sendError("Invalid phone number", 400, "INVALID_PHONE");
}

/* -------------------------------------------------
   CHECK PROFILE ALREADY EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM business_profiles 
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    sendError("Business profile already exists", 409, "PROFILE_EXISTS");
}

/* -------------------------------------------------
   DUPLICATE PHONE CHECK
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM business_profiles
    WHERE phone = ?
    LIMIT 1
");
$stmt->execute([$data['phone']]);
if ($stmt->fetch()) {
    sendError("Phone number already used", 409, "DUPLICATE_PHONE");
}

/* -------------------------------------------------
   LOGO UPLOAD (OPTIONAL)
------------------------------------------------- */
$logoPath = null;
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
   COVER IMAGE UPLOAD (OPTIONAL - ADDED)
------------------------------------------------- */
$coverImagePath = null;
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
   WEBSITE (OPTIONAL - ADDED)
------------------------------------------------- */
$website = null;
if (!empty($data['website'])) {
    if (!filter_var($data['website'], FILTER_VALIDATE_URL)) {
        sendError("Invalid website URL", 400, "INVALID_WEBSITE");
    }
    $website = strtolower(trim($data['website']));
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO business_profiles
        (
            user_id,
            business_name,
            logo,
            cover_image,
            short_bio,
            category_id,
            sub_category_id,
            city,
            area,
            phone,
            whatsapp,
            website,
            profile_completion,
            status,
            created_at
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,100,'ACTIVE',NOW())
    ");

    $stmt->execute([
        $user_id,
        strtolower(trim($data['business_name'])),
        $logoPath,
        $coverImagePath,
        strtolower(trim($data['short_bio'])),
        (int)$data['category_id'],
        (int)$data['sub_category_id'],
        strtolower(trim($data['city'])),
        strtolower(trim($data['area'])),
        $data['phone'],
        $data['phone'],
        $website
    ]);

    $pdo->commit();
    sendSuccess("Business profile created successfully");

} catch (Throwable $e) {
    $pdo->rollBack();
    sendError("Failed to create business profile", 500, "SERVER_ERROR");
}
