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
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
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

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if (!$deal_id) {
    sendError("deal_id required", 422, "VALIDATION_ERROR");
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
   ACCESS CONTROL (ONLY INITIATOR 🔥)
------------------------------------------------- */
if ((int)$deal['initiator_id'] !== $user_id) {
    sendError("Only deal creator can complete the deal", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   STATE VALIDATION
------------------------------------------------- */
if ($deal['status'] !== 'ACCEPTED') {
    sendError("Only accepted deals can be completed", 409, "INVALID_STATE");
}

/* -------------------------------------------------
   GET USER NAME (FOR NOTIFICATION)
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);

$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $userData['name'] ?? 'User';

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* UPDATE DEAL */
    $stmt = $pdo->prepare("
        UPDATE vendor_deals 
        SET status = 'COMPLETED', updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$deal_id]);

    /* NOTIFY RECEIVER */
    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'DEAL_COMPLETED', ?, ?)
    ");

    $stmt->execute([
        $deal['receiver_id'],
        $user_id,
        $deal_id,
        "{$user_name} marked the deal as completed"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Failed to complete deal", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Deal marked as completed", [
    "deal_id" => $deal_id,
    "status"  => "COMPLETED"
]);