<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed", 405);
}

/* AUTH */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$user_id = (int)$user['id'];

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON", 400);
}

$message_id = (int)($input['message_id'] ?? 0);

/* VALIDATION */
if (!$message_id) {
    sendError("message_id required", 422);
}

/* -------------------------------------------------
   FETCH MESSAGE + DEAL
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT vdm.id, vdm.sender_id, vdm.deal_id,
           vd.initiator_id, vd.receiver_id
    FROM vendor_deal_messages vdm
    JOIN vendor_deals vd ON vd.id = vdm.deal_id
    WHERE vdm.id = ?
");
$stmt->execute([$message_id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    sendError("Message not found", 404);
}

/* -------------------------------------------------
   ACCESS CHECK (deal participant)
------------------------------------------------- */
if (
    $user_id != $data['initiator_id'] &&
    $user_id != $data['receiver_id']
) {
    sendError("Access denied", 403);
}

/* -------------------------------------------------
   ONLY SENDER CAN DELETE
------------------------------------------------- */
if ($user_id != $data['sender_id']) {
    sendError("You can delete only your own message", 403);
}

/* -------------------------------------------------
   DELETE MESSAGE
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        DELETE FROM vendor_deal_messages
        WHERE id = ?
    ");
    $stmt->execute([$message_id]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Failed to delete message", 500);
}

/* RESPONSE */
sendSuccess("Message deleted successfully", [
    "message_id" => $message_id
]);