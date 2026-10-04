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

/* INPUT */
$community_id = (int)($_GET['community_id'] ?? 0);
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

/* VALIDATION */
if (!$community_id) {
    sendError("community_id required", 422);
}

/* CHECK COMMUNITY */
$stmt = $pdo->prepare("
    SELECT id FROM communities 
    WHERE id = ? AND status = 'ACTIVE'
");
$stmt->execute([$community_id]);

if (!$stmt->fetch()) {
    sendError("Community not found", 404);
}

/* ACCESS CHECK (MEMBER ONLY) */
$stmt = $pdo->prepare("
    SELECT id FROM community_members 
    WHERE community_id = ? AND user_id = ?
");
$stmt->execute([$community_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Access denied. Not a member", 403);
}

/* TOTAL COUNT */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM community_messages 
    WHERE community_id = ?
");
$countStmt->execute([$community_id]);

$total = (int)$countStmt->fetchColumn();

/* FETCH MESSAGES (LATEST FIRST) */
$stmt = $pdo->prepare("
    SELECT 
        cm.id,
        cm.community_id,
        cm.sender_id,
        cm.message,
        cm.message_type,
        cm.created_at,

        u.name AS sender_name,
        u.mobile AS sender_mobile

    FROM community_messages cm
    JOIN users u ON u.id = cm.sender_id
    WHERE cm.community_id = ?
    ORDER BY cm.id DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute([$community_id]);

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* OPTIONAL: REVERSE FOR CHAT UI (old → new) */
$messages = array_reverse($messages);

/* RESPONSE */
sendSuccess("Messages fetched successfully", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $messages
]);