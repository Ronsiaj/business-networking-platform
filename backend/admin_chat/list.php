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
   ADMIN VIEW : ALL BUSINESS CHATS
------------------------------------------------- */
if ($role === 'admin') {

    $stmt = $pdo->prepare("
        SELECT
            bp.id AS business_profile_id,
            bp.business_name,
            bp.logo,
            am.business_user_id,

            (
                SELECT message
                FROM admin_messages
                WHERE business_user_id = am.business_user_id
                ORDER BY created_at DESC
                LIMIT 1
            ) AS last_message,

            (
                SELECT created_at
                FROM admin_messages
                WHERE business_user_id = am.business_user_id
                ORDER BY created_at DESC
                LIMIT 1
            ) AS last_message_at

        FROM admin_messages am
        JOIN business_profiles bp
            ON bp.user_id = am.business_user_id

        GROUP BY am.business_user_id
        ORDER BY last_message_at DESC
    ");

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendSuccess("Admin chat list loaded", [
        "count" => count($rows),
        "list"  => array_map(function ($r) {
            return [
                "business_profile_id" => (int)$r['business_profile_id'],
                "business_user_id"    => (int)$r['business_user_id'],
                "business_name"       => $r['business_name'],
                "logo"                => $r['logo'],
                "last_message"        => $r['last_message'],
                "last_message_at"     => $r['last_message_at']
            ];
        }, $rows)
    ]);
}

/* -------------------------------------------------
   BUSINESS VIEW : SINGLE ADMIN CHAT
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        bp.id AS business_profile_id,
        bp.business_name,
        bp.logo,

        (
            SELECT message
            FROM admin_messages
            WHERE business_user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ) AS last_message,

        (
            SELECT created_at
            FROM admin_messages
            WHERE business_user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ) AS last_message_at
    FROM business_profiles bp
    WHERE bp.user_id = ?
    LIMIT 1
");

$stmt->execute([$user_id, $user_id, $user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    sendSuccess("Admin chat list loaded", [
        "count" => 0,
        "list"  => []
    ]);
}

sendSuccess("Admin chat list loaded", [
    "count" => 1,
    "list"  => [[
        "business_profile_id" => (int)$row['business_profile_id'],
        "business_user_id"    => $user_id,
        "business_name"       => $row['business_name'],
        "logo"                => $row['logo'],
        "last_message"        => $row['last_message'],
        "last_message_at"     => $row['last_message_at']
    ]]
]);
