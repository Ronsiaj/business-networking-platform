<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* -------------------------
   PREFLIGHT
------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* -------------------------
   DEPENDENCIES
------------------------- */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------
   METHOD CHECK
------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405);
}

/* -------------------------
   AUTH CHECK
------------------------- */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

if (($user['role'] ?? '') !== 'admin') {
    sendError("Access denied. Admin only", 403);
}

/* -------------------------
   INPUTS
------------------------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = strtolower(trim($_GET['q'] ?? ''));

/* -------------------------
   SEARCH FILTER
------------------------- */
$whereSearch = "";
$params = [];

if ($q !== '') {
    $whereSearch = " WHERE (
        LOWER(c.name) LIKE ?
        OR LOWER(c.description) LIKE ?
        OR LOWER(u.name) LIKE ?
        OR LOWER(u.mobile) LIKE ?
    )";

    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

/* -------------------------
   TOTAL COUNT
------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(c.id)
    FROM communities c
    LEFT JOIN users u ON u.id = c.created_by
    {$whereSearch}
");

$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------
   FETCH DATA
------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.description,
        c.type,
        c.status,
        c.created_at,

        c.created_by,

        u.name AS created_by_name,
        u.mobile AS created_by_mobile,
        u.email AS created_by_email,

        (SELECT COUNT(*) 
         FROM community_members cm 
         WHERE cm.community_id = c.id) AS total_members

    FROM communities c
    LEFT JOIN users u ON u.id = c.created_by

    {$whereSearch}

    ORDER BY c.id DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   RESPONSE
------------------------- */
sendSuccess("Communities fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);