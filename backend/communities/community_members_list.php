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

$user_id = (int)$user['id'];
$user_role = $user['role'] ?? '';

/* INPUT */
$community_id = (int)($_GET['community_id'] ?? 0);
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = strtolower(trim($_GET['q'] ?? ''));

/* VALIDATION */
if (!$community_id) {
    sendError("community_id required", 422);
}

/* CHECK COMMUNITY EXISTS */
$stmt = $pdo->prepare("
    SELECT id FROM communities 
    WHERE id = ? AND status = 'ACTIVE'
");
$stmt->execute([$community_id]);

if (!$stmt->fetch()) {
    sendError("Community not found", 404);
}

/* ACCESS CHECK */
if ($user_role !== 'admin') {

    // normal user → must be member
    $stmt = $pdo->prepare("
        SELECT id FROM community_members 
        WHERE community_id = ? AND user_id = ?
    ");
    $stmt->execute([$community_id, $user_id]);

    if (!$stmt->fetch()) {
        sendError("Access denied. Not a community member", 403);
    }
}

/* SEARCH FILTER */
$whereSearch = "";
$params = [$community_id];

if ($q !== '') {
    $whereSearch = " AND (
        LOWER(u.name) LIKE ?
        OR LOWER(u.mobile) LIKE ?
        OR LOWER(c.name) LIKE ?
    )";

    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

/* TOTAL COUNT */
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT cm.user_id)
    FROM community_members cm
    JOIN users u ON u.id = cm.user_id
    LEFT JOIN user_interest_map uim ON uim.user_id = u.id
    LEFT JOIN categories c ON c.id = uim.category_id
    WHERE cm.community_id = ? {$whereSearch}
");
$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();

/* FETCH MEMBERS */
$stmt = $pdo->prepare("
    SELECT 
        cm.user_id,
        cm.role,
        cm.joined_at,

        u.name,
        u.mobile,
        u.email,
        
        GROUP_CONCAT(DISTINCT c.name) AS categories

    FROM community_members cm
    JOIN users u ON u.id = cm.user_id
    LEFT JOIN user_interest_map uim ON uim.user_id = u.id
    LEFT JOIN categories c ON c.id = uim.category_id

    WHERE cm.community_id = ? {$whereSearch}

    GROUP BY cm.user_id
    ORDER BY cm.id DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* RESPONSE */
sendSuccess("Community members fetched", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);