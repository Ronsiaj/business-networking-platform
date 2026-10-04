<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

require_auth();
$authUser = $GLOBALS['auth_user'];
$user_id  = (int)$authUser['id'];
$role     = $authUser['role'];

if (!in_array($role, ['admin', 'business'])) {
    sendError("Access denied", 403);
}

/* Determine scope */
if ($role === 'admin') {
    $business_user_id = (int)($_GET['business_user_id'] ?? 0);
    if ($business_user_id <= 0) sendError("business_user_id required");
} else {
    $business_user_id = $user_id;
}

/* Header profile */
$stmt = $pdo->prepare("SELECT id, business_name, logo FROM business_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$business_user_id]);
$bp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bp) sendError("Business profile not found");

/* Fetch messages — exclude deleted ones based on viewer role */
if ($role === 'admin') {
    $stmt = $pdo->prepare("
        SELECT id, message, sender_role, created_at
        FROM admin_messages
        WHERE business_user_id = ?
          AND deleted_for_everyone = 0
          AND deleted_by_admin = 0
        ORDER BY created_at ASC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT id, message, sender_role, created_at
        FROM admin_messages
        WHERE business_user_id = ?
          AND deleted_for_everyone = 0
          AND deleted_by_business = 0
        ORDER BY created_at ASC
    ");
}
$stmt->execute([$business_user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$list = [];
foreach ($rows as $r) {
    $list[] = [
        "id"          => (int)$r['id'],
        "text"        => $r['message'],
        "sender_role" => $r['sender_role'],
        "is_me"       => ($r['sender_role'] === $role),
        "created_at"  => $r['created_at']
    ];
}

sendSuccess("Messages loaded successfully", [
    "header_profile" => [
        "type"                => "business",
        "business_profile_id" => (int)$bp['id'],
        "business_name"       => $bp['business_name'],
        "logo"                => $bp['logo']
    ],
    "count" => count($list),
    "list"  => $list
]);