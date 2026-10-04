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
   AUTH : COMMON (ADMIN / BUSINESS / STAFF)
------------------------------------------------- */
require_auth();

/* -------------------------------------------------
   INPUT (QUERY PARAMS)
------------------------------------------------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$search          = strtolower(trim($_GET['search'] ?? ''));
$category_id     = $_GET['category_id'] ?? null;
$sub_category_id = $_GET['sub_category_id'] ?? null;
$status          = $_GET['status'] ?? null;

/* -------------------------------------------------
   BUILD WHERE CONDITIONS
------------------------------------------------- */
$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(bp.business_name LIKE ? 
                 OR bp.phone LIKE ? 
                 OR bp.city LIKE ? 
                 OR bp.area LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($category_id)) {
    $where[] = "bp.category_id = ?";
    $params[] = (int)$category_id;
}

if (!empty($sub_category_id)) {
    $where[] = "bp.sub_category_id = ?";
    $params[] = (int)$sub_category_id;
}

if (!empty($status)) {
    $where[] = "bp.status = ?";
    $params[] = strtoupper($status);
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM business_profiles bp
    {$whereSql}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   DATA QUERY
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        bp.id,
        bp.user_id,
        bp.business_name,
        bp.logo,
        bp.city,
        bp.area,
        bp.phone,
        bp.category_id,
        bp.sub_category_id,
        bp.profile_completion,
        bp.status,
        bp.is_verified, 
        bp.created_at
    FROM business_profiles bp
    {$whereSql}
    ORDER BY bp.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Business list fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int)ceil($total / $limit)
    ],
    "list" => $list
]);
