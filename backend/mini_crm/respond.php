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

/* -------------------------------------------------
   DEPENDENCIES
------------------------------------------------- */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed", 405, "METHOD_NOT_ALLOWED");
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
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON", 400, "INVALID_JSON");
}

$deal_id = (int)($input['deal_id'] ?? 0);
$status  = strtoupper(trim($input['status'] ?? ''));

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if (!$deal_id) {
    sendError("deal_id required", 422, "VALIDATION_ERROR");
}

if (!in_array($status, ['ACCEPTED', 'REJECTED'])) {
    sendError("Invalid status (ACCEPTED / REJECTED only)", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   CHECK DEAL
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, initiator_id, receiver_id, status 
    FROM vendor_deals 
    WHERE id = ?
");
$stmt->execute([$deal_id]);

$deal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deal) {
    sendError("Deal not found", 404, "NOT_FOUND");
}

/* -------------------------------------------------
   ACCESS CHECK (ONLY RECEIVER)
------------------------------------------------- */
if ((int)$deal['receiver_id'] !== $user_id) {
    sendError("Only receiver can respond", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   STATE VALIDATION
------------------------------------------------- */
if ($deal['status'] !== 'PENDING') {
    sendError("Deal already responded", 409, "INVALID_STATE");
}

/* -------------------------------------------------
   GET USER NAMES (FOR NOTIFICATION)
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);

$receiver = $stmt->fetch(PDO::FETCH_ASSOC);
$receiver_name = $receiver['name'] ?? 'User';

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* UPDATE DEAL STATUS */
    $stmt = $pdo->prepare("
        UPDATE vendor_deals 
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$status, $deal_id]);

    /* NOTIFY INITIATOR */
    $messageText = ($status === 'ACCEPTED')
        ? "{$receiver_name} accepted your deal request"
        : "{$receiver_name} rejected your deal request";

    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'DEAL_RESPONSE', ?, ?)
    ");

    $stmt->execute([
        $deal['initiator_id'], // receiver → initiatorக்கு notify
        $user_id,
        $deal_id,
        $messageText
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Failed to update deal", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Deal {$status}", [
    "deal_id" => $deal_id,
    "status"  => $status
]);