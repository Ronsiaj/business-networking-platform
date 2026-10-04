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

$sender_id = (int)$user['id'];

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON", 400);
}

$deal_id = (int)($input['deal_id'] ?? 0);
$message = trim($input['message'] ?? '');

/* VALIDATION */
if (!$deal_id) {
    sendError("deal_id required", 422);
}

if ($message === '') {
    sendError("Message cannot be empty", 422);
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
    sendError("Deal not found", 404);
}

/* access */
if ($sender_id != $deal['initiator_id'] && $sender_id != $deal['receiver_id']) {
    sendError("Access denied", 403);
}

/* only accepted deal */
if ($deal['status'] !== 'ACCEPTED') {
    sendError("Deal not accepted yet", 403);
}

/* identify receiver */
$receiver_id = ($sender_id == $deal['initiator_id'])
    ? (int)$deal['receiver_id']
    : (int)$deal['initiator_id'];

/* sender name */
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$sender_id]);

$sender = $stmt->fetch(PDO::FETCH_ASSOC);
$sender_name = $sender['name'] ?? 'User';

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* INSERT MESSAGE */
    $stmt = $pdo->prepare("
        INSERT INTO vendor_deal_messages
        (deal_id, sender_id, receiver_id, message)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $deal_id,
        $sender_id,
        $receiver_id,
        $message
    ]);

    $message_id = (int)$pdo->lastInsertId();

    /* NOTIFICATION (only to receiver 🔥) */
    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'DEAL_MESSAGE', ?, ?)
    ");

    $stmt->execute([
        $receiver_id,
        $sender_id,
        $deal_id,
        "{$sender_name} sent you a message"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Failed to send message", 500);
}

/* RESPONSE */
sendSuccess("Message sent", [
    "message" => [
        "id" => $message_id,
        "deal_id" => $deal_id,
        "sender_id" => $sender_id,
        "receiver_id" => $receiver_id,
        "message" => $message
    ]
]);