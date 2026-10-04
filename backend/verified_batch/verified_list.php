<?php
header("Content-Type: application/json; charset=utf-8");
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
   DEPENDENCIES
------------------------------------------------- */
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   INPUT (Pagination + Search)
------------------------------------------------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = strtolower(trim($_GET['q'] ?? ''));

/* -------------------------------------------------
   WHERE (ONLY VERIFIED)
------------------------------------------------- */
$where = "bp.is_verified = 1";
$params = [];

/* -------------------------------------------------
   SEARCH FILTER
------------------------------------------------- */
if ($q !== '') {
    $where .= " AND (
        LOWER(bp.business_name) LIKE ?
        OR LOWER(bp.city) LIKE ?
    )";

    $search = "%{$q}%";
    array_push($params, $search, $search);
}

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM business_profiles bp
    WHERE $where
");

$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH LIST
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        bp.id,
        bp.business_name,
        bp.city,
        bp.is_verified,
        bp.verified_at

    FROM business_profiles bp
    WHERE $where
    ORDER BY bp.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Verified businesses fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $list
]);