<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

$search = strtolower(trim($_GET['search'] ?? ''));

/* -------------------------------------------------
   BUILD WHERE
------------------------------------------------- */
$where  = ["status = 'ACTIVE'"];
$params = [];

if ($search !== '') {
    $where[] = "name LIKE ?";
    $params[] = "%{$search}%";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM categories
    {$whereSql}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH CATEGORIES (PAGINATED + ID ORDER)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        icon
    FROM categories
    {$whereSql}
    ORDER BY id ASC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Categories fetched successfully",
    [
        "pagination" => [
            "page"  => $page,
            "limit" => $limit,
            "total" => $total,
            "pages" => (int) ceil($total / $limit)
        ],
        "list" => $categories
    ]
);
