<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH : COMMON (ADMIN / BUSINESS / STAFF)
------------------------------------------------- */
require_auth();

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$profile_id = $_GET['id'] ?? null;
$user_id    = $_GET['user_id'] ?? null;

if (!$profile_id && !$user_id) {
    sendError(
        "id or user_id is required",
        400,
        "MISSING_IDENTIFIER"
    );
}

/* -------------------------------------------------
   FETCH BUSINESS PROFILE
------------------------------------------------- */
if ($profile_id) {
    $stmt = $pdo->prepare("
        SELECT
            bp.id,
            bp.user_id,
            bp.business_name,
            bp.logo,
            bp.cover_image,
            bp.short_bio,
            bp.category_id,
            bp.sub_category_id,
            bp.city,
            bp.area,
            bp.phone,
            bp.whatsapp,
            bp.website,
            bp.profile_completion,
            bp.status,
            bp.created_at,
            bp.updated_at
        FROM business_profiles bp
        WHERE bp.id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$profile_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT
            bp.id,
            bp.user_id,
            bp.business_name,
            bp.logo,
            bp.cover_image,
            bp.short_bio,
            bp.category_id,
            bp.sub_category_id,
            bp.city,
            bp.area,
            bp.phone,
            bp.whatsapp,
            bp.website,
            bp.profile_completion,
            bp.status,
            bp.created_at,
            bp.updated_at
        FROM business_profiles bp
        WHERE bp.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$user_id]);
}

$profile = $stmt->fetch(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   NOT FOUND
------------------------------------------------- */
if (!$profile) {
    sendError(
        "Business profile not found",
        404,
        "PROFILE_NOT_FOUND"
    );
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Business profile fetched successfully", [
    "business_profile" => [
        "id"                 => (int)$profile['id'],
        "user_id"            => (int)$profile['user_id'],
        "business_name"      => $profile['business_name'],
        "logo"               => $profile['logo'],
        "cover_image"        => $profile['cover_image'],
        "short_bio"          => $profile['short_bio'],
        "category_id"        => (int)$profile['category_id'],
        "sub_category_id"    => (int)$profile['sub_category_id'],
        "city"               => $profile['city'],
        "area"               => $profile['area'],
        "phone"              => $profile['phone'],
        "whatsapp"           => $profile['whatsapp'],
        "website"            => $profile['website'],
        "profile_completion" => (int)$profile['profile_completion'],
        "status"             => $profile['status'],
        "created_at"         => $profile['created_at'],
        "updated_at"         => $profile['updated_at']
    ]
]);
