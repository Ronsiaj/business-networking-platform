<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

require_admin();

/* ================= INPUT ================= */
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 10)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$search = trim($_GET['search'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

$conditions = [];
$params = [];

/* 🔍 Business search */
if ($search !== '') {
    $conditions[] = "(LOWER(bp1.business_name) LIKE ? OR LOWER(bp2.business_name) LIKE ?)";
    $params[] = "%".strtolower($search)."%";
    $params[] = "%".strtolower($search)."%";
}

/* 📅 Date filters */
if ($from !== '') {
    $conditions[] = "DATE(cal.last_message_at) >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $conditions[] = "DATE(cal.last_message_at) <= ?";
    $params[] = $to;
}

$whereSql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

/* ================= FETCH DATA ================= */
$stmt = $pdo->prepare("
    SELECT
        cal.chat_id,
        cal.business_one,
        cal.business_two,
        cal.message_count,
        cal.last_message_at,
        c.created_at AS chat_created_at,

        u1.role AS role_one,
        u2.role AS role_two,

        bp1.id AS business_one_profile_id,
        bp1.business_name AS business_one_name,

        bp2.id AS business_two_profile_id,
        bp2.business_name AS business_two_name

    FROM chat_activity_log cal
    JOIN chats c ON c.id = cal.chat_id
    JOIN users u1 ON u1.id = cal.business_one
    JOIN users u2 ON u2.id = cal.business_two
    LEFT JOIN business_profiles bp1 ON bp1.user_id = cal.business_one
    LEFT JOIN business_profiles bp2 ON bp2.user_id = cal.business_two

    $whereSql
    ORDER BY cal.last_message_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= FORMAT ================= */
$list = [];

foreach ($rows as $r) {

    $businessOne = ($r['role_one'] === 'admin')
        ? ["type"=>"admin","business_profile_id"=>null,"business_name"=>"Admin"]
        : ["type"=>"business","business_profile_id"=>(int)$r['business_one_profile_id'],"business_name"=>$r['business_one_name']];

    $businessTwo = ($r['role_two'] === 'admin')
        ? ["type"=>"admin","business_profile_id"=>null,"business_name"=>"Admin"]
        : ["type"=>"business","business_profile_id"=>(int)$r['business_two_profile_id'],"business_name"=>$r['business_two_name']];

    $list[] = [
        "chat_id" => (int)$r['chat_id'],
        "business_one" => $businessOne,
        "business_two" => $businessTwo,
        "message_count" => (int)$r['message_count'],
        "last_message_at" => $r['last_message_at'],
        "chat_created_at" => $r['chat_created_at']
    ];
}

sendSuccess("Chat activity log fetched successfully", [
    "count" => count($list),
    "list"  => $list
]);
