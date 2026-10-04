<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


/* HANDLE PREFLIGHT REQUEST */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';


/* -------------------------------------------------
   AUTH : ADMIN ONLY
------------------------------------------------- */
require_admin();

/* -------------------------------------------------
   INPUT (QUERY PARAMS)
------------------------------------------------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$search    = trim($_GET['search'] ?? '');
$status    = trim($_GET['status'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date   = trim($_GET['to_date'] ?? '');

/* -------------------------------------------------
   BUILD CONDITIONS
------------------------------------------------- */
$conditions = ["u.role = 'business'"];
$params = [];

/* Search by business name or user id */
if ($search !== '') {
    $conditions[] = "(LOWER(bp.business_name) LIKE ? OR u.id = ?)";
    $params[] = '%' . strtolower($search) . '%';
    $params[] = (int)$search;
}

/* Status filter */
if ($status !== '') {
    $conditions[] = "u.status = ?";
    $params[] = $status;
}

/* Date filters */
if ($from_date !== '') {
    $conditions[] = "DATE(u.created_at) >= ?";
    $params[] = $from_date;
}

if ($to_date !== '') {
    $conditions[] = "DATE(u.created_at) <= ?";
    $params[] = $to_date;
}

$whereSql = implode(' AND ', $conditions);

/* -------------------------------------------------
   TOTAL COUNT (FOR PAGINATION)
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users u
    LEFT JOIN business_profiles bp ON bp.user_id = u.id
    WHERE $whereSql
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH DATA
------------------------------------------------- */
$dataStmt = $pdo->prepare("
    SELECT
        u.id   AS user_id,
        u.name AS user_name,
        u.status,
        u.created_at,

        bp.id  AS business_profile_id,
        bp.business_name,
        bp.logo

    FROM users u
    LEFT JOIN business_profiles bp
        ON bp.user_id = u.id

    WHERE $whereSql
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   FORMAT RESPONSE
------------------------------------------------- */
$list = [];

foreach ($rows as $r) {
    $list[] = [
    "user_id" => (int)$r['user_id'],

    "business_profile" => [
        "business_profile_id" => $r['business_profile_id']
            ? (int)$r['business_profile_id']
            : null,
        "business_name" => $r['business_name'] ?? $r['user_name'],  // fallback to u.name
        "logo" => $r['logo']
    ],

    "status" => $r['status'],
    "joined_at" => $r['created_at']
];
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Business users fetched successfully", [
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total_records" => $totalRecords,
        "total_pages" => ceil($totalRecords / $limit)
    ],
    "count" => count($list),
    "list"  => $list
]);
