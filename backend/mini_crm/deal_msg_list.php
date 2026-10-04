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
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH
------------------------------------------------- */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$user_id = (int)$user['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$deal_id = (int)($_GET['deal_id'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset  = ($page - 1) * $limit;

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if (!$deal_id) {
    sendError("deal_id required", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   CHECK DEAL + ACCESS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT initiator_id, receiver_id, status
    FROM vendor_deals
    WHERE id = ?
");
$stmt->execute([$deal_id]);

$deal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deal) {
    sendError("Deal not found", 404, "NOT_FOUND");
}

/* Only participants allowed */
if ($user_id != $deal['initiator_id'] && $user_id != $deal['receiver_id']) {
    sendError("Access denied", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   GET DEALER NAME (OTHER USER)
------------------------------------------------- */
$other_user_id = ($user_id == $deal['initiator_id'])
    ? $deal['receiver_id']
    : $deal['initiator_id'];

$stmt = $pdo->prepare("
    SELECT id, name
    FROM users
    WHERE id = ?
");
$stmt->execute([$other_user_id]);

$dealer = $stmt->fetch(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM vendor_deal_messages
    WHERE deal_id = ?
");
$countStmt->execute([$deal_id]);

$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH MESSAGES
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        vdm.id,
        vdm.deal_id,
        vdm.sender_id,
        vdm.receiver_id,
        vdm.message,
        vdm.created_at,

        u.name AS sender_name,
        u.mobile AS sender_mobile

    FROM vendor_deal_messages vdm
    JOIN users u ON u.id = vdm.sender_id
    WHERE vdm.deal_id = ?
    ORDER BY vdm.id DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute([$deal_id]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   FORMAT (reverse for chat order)
------------------------------------------------- */
$data = array_reverse($data);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Deal messages fetched", [
    "deal_id" => $deal_id,

    // ✅ THIS IS WHAT YOU NEEDED
    "dealer_name" => $dealer['name'] ?? null,
    "dealer_id"   => $dealer['id'] ?? null,

    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],

    "messages" => $data
]);