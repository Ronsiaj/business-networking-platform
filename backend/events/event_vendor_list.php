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
$event_id = (int)($_GET['event_id'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset   = ($page - 1) * $limit;

$status_filter = strtoupper(trim($_GET['status'] ?? ''));

/* VALIDATION */
if (!$event_id) {
    sendError("event_id required", 422);
}

/* OPTIONAL STATUS FILTER */
$whereStatus = "";
$params = [$event_id];

if ($status_filter !== '') {
    if (!in_array($status_filter, ['INVITED','ACCEPTED','REJECTED'])) {
        sendError("Invalid status filter", 422);
    }
    $whereStatus = " AND ev.status = ? ";
    $params[] = $status_filter;
}

/* ACCESS CHECK */
$access = false;

/* check client */
$stmt = $pdo->prepare("
    SELECT id FROM events 
    WHERE id = ? AND client_id = ?
");
$stmt->execute([$event_id, $user_id]);

if ($stmt->fetch()) {
    $access = true;
}

/* check vendor participation */
if (!$access) {
    $stmt = $pdo->prepare("
        SELECT id FROM event_vendors 
        WHERE event_id = ? AND vendor_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);

    if ($stmt->fetch()) {
        $access = true;
    }
}

if (!$access) {
    sendError("Access denied for this event", 403);
}

/* TOTAL COUNT */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM event_vendors ev
    WHERE ev.event_id = ? {$whereStatus}
");
$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();

/* FETCH LIST */
$stmt = $pdo->prepare("
    SELECT 
        ev.id AS event_vendor_id,
        ev.vendor_id,
        ev.service_type,
        ev.status,
        ev.joined_at,

        u.name,
        u.mobile AS vendor_mobile,  -- ✅ ONLY CHANGE
        u.email

    FROM event_vendors ev
    JOIN users u ON u.id = ev.vendor_id
    WHERE ev.event_id = ? {$whereStatus}
    ORDER BY ev.id DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* RESPONSE */
sendSuccess("Event vendor list fetched", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);