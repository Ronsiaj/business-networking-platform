<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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
   FETCH UNREAD COUNT (ADMIN CHAT)
------------------------------------------------- */
if ($role === 'admin') {

    // Admin → unread messages sent by businesses
    $stmt = $pdo->prepare("
        SELECT
            am.business_user_id,
            COUNT(am.id) AS unread_count
        FROM admin_messages am
        WHERE am.admin_id = ?
          AND am.is_read = 0
        GROUP BY am.business_user_id
    ");
    $stmt->execute([$user_id]);

} else {

    // Business → unread messages sent by admin
    $stmt = $pdo->prepare("
        SELECT
            am.business_user_id,
            COUNT(am.id) AS unread_count
        FROM admin_messages am
        WHERE am.business_user_id = ?
          AND am.admin_id IS NOT NULL
          AND am.is_read = 0
        GROUP BY am.business_user_id
    ");
    $stmt->execute([$user_id]);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE FORMAT
------------------------------------------------- */
$list = [];

foreach ($rows as $r) {
    $list[] = [
        "business_user_id" => (int)$r['business_user_id'],
        "unread_count"     => (int)$r['unread_count']
    ];
}

sendSuccess("Admin chat unread count fetched", [
    "count" => count($list),
    "list"  => $list
]);
