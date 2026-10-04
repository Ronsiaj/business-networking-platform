<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed", 405);
}

require_auth();

$user = $GLOBALS['auth_user'] ?? null;
if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$user_id = (int)$user['id'];

$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.description,
        c.type,
        c.status,
        c.created_by,
        c.created_at,
        COALESCE(cm.role, 'ADMIN') AS role,
        COALESCE(cm.joined_at, c.created_at) AS joined_at,
        (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) AS total_members
    FROM communities c
    LEFT JOIN community_members cm ON cm.community_id = c.id AND cm.user_id = ?
    WHERE c.status = 'ACTIVE'
    AND (
        cm.user_id = ?
        OR c.created_by = ?
    )
    ORDER BY c.id DESC
");

$stmt->execute([$user_id, $user_id, $user_id]);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

sendSuccess("Communities fetched", ["list" => $list]);