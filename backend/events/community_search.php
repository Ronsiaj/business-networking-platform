<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* PREFLIGHT */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* DEPENDENCIES */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* METHOD */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405);
}

/* AUTH */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

/* INPUT */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = trim($_GET['q'] ?? '');

/* VALIDATION */
$where = ["c.status = 'ACTIVE'"];
$params = [];

if ($q !== '') {
    if (strlen($q) < 2) {
        sendError("Search query must be at least 2 characters", 422);
    }

    $where[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

$whereSql = "WHERE " . implode(" AND ", $where);

/* TOTAL */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM communities c
    {$whereSql}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* FETCH */
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.description,
        c.type,
        c.created_by,
        c.created_at,
        u.name AS creator_name,
        u.mobile AS creator_mobile
    FROM communities c
    LEFT JOIN users u ON u.id = c.created_by
    {$whereSql}
    ORDER BY c.id DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* RESPONSE */
sendSuccess("Communities fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);