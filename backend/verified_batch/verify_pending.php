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
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH (ADMIN ONLY)
------------------------------------------------- */
require_admin();

$user = $GLOBALS['auth_user'] ?? null;
if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$admin_id = (int)$user['id'];

/* -------------------------------------------------
   INPUT (Pagination + Search)
------------------------------------------------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = strtolower(trim($_GET['q'] ?? ''));

/* -------------------------------------------------
   BASE WHERE (ONLY PENDING)
------------------------------------------------- */
$where = "bp.verification_status = 'PENDING'";
$params = [];

/* -------------------------------------------------
   SEARCH FILTER
------------------------------------------------- */
if ($q !== '') {
    $where .= " AND (
        LOWER(bp.business_name) LIKE ?
        OR LOWER(bp.gst_number) LIKE ?
        OR LOWER(bp.license_number) LIKE ?
        OR LOWER(u.name) LIKE ?
        OR LOWER(u.mobile) LIKE ?
    )";

    $search = "%{$q}%";
    array_push($params, $search, $search, $search, $search, $search);
}

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM business_profiles bp
    INNER JOIN users u ON u.id = bp.user_id
    WHERE $where
");

$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH DATA
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        bp.id,
        bp.user_id,
        bp.business_name,
        bp.gst_number,
        bp.license_number,
        bp.city,
        bp.created_at,
        bp.verification_status,

        u.name AS owner_name,
        u.mobile,
        u.email

    FROM business_profiles bp
    INNER JOIN users u ON u.id = bp.user_id

    WHERE $where

    ORDER BY bp.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Pending verification list fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);