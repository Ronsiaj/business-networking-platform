<?php
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
   DEPENDENCIES
------------------------------------------------- */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405);
}

/* -------------------------------------------------
   AUTH
------------------------------------------------- */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$user_id   = (int)$user['id'];
$user_role = $user['role'] ?? '';

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = strtolower(trim($_GET['q'] ?? ''));

/* -------------------------------------------------
   WHERE BUILD
------------------------------------------------- */
$where = [];
$params = [];

/* ROLE BASED ACCESS */
if ($user_role !== 'admin') {
    $where[] = "e.client_id = ?";
    $params[] = $user_id;
}

/* SEARCH */
if ($q !== '') {
    $where[] = "(
        LOWER(e.title) LIKE ?
        OR LOWER(e.description) LIKE ?
        OR LOWER(e.status) LIKE ?
        OR DATE_FORMAT(e.event_date, '%Y-%m-%d') LIKE ?
    )";

    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

/* FINAL WHERE */
$whereSql = '';
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM events e
    {$whereSql}
");
$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH EVENTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.client_id,
        e.title,
        e.description,
        e.event_date,
        e.status,
        e.created_at,

        u.name AS client_name,
        u.mobile AS client_mobile

    FROM events e
    JOIN users u ON u.id = e.client_id

    {$whereSql}

    ORDER BY e.id DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Events fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $events
]);