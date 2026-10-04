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

$user_id   = (int)$user['id'];
$user_role = strtolower($user['role'] ?? '');

/* INPUT (pagination + search) */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = strtolower(trim($_GET['q'] ?? ''));

/* -------------------------------------------------
   BUILD WHERE
------------------------------------------------- */
$where = "1=1";
$params = [];

/* ROLE FILTER */
if ($user_role !== 'admin') {

    $where .= " AND (
        vd.initiator_id = ?
        OR (vd.receiver_id = ? AND vd.status = 'ACCEPTED')
    )";

    $params[] = $user_id;
    $params[] = $user_id;
}

/* SEARCH */
if ($q !== '') {
    $where .= " AND (
        LOWER(u1.name) LIKE ?
        OR LOWER(u2.name) LIKE ?
        OR LOWER(vd.title) LIKE ?
    )";

    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM vendor_deals vd
    JOIN users u1 ON u1.id = vd.initiator_id
    JOIN users u2 ON u2.id = vd.receiver_id
    WHERE $where
");

$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH LIST
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        vd.id,
        vd.title,
        vd.description,
        vd.status,
        vd.created_at,

        vd.initiator_id,
        u1.name AS initiator_name,

        vd.receiver_id,
        u2.name AS receiver_name

    FROM vendor_deals vd
    JOIN users u1 ON u1.id = vd.initiator_id
    JOIN users u2 ON u2.id = vd.receiver_id

    WHERE $where

    ORDER BY vd.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);

$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Deals fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $list
]);