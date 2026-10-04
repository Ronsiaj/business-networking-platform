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

$initiator_id = (int)$user['id'];

/* CHECK INITIATOR ACTIVE */
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'ACTIVE'");
$stmt->execute([$initiator_id]);

if (!$stmt->fetch()) {
    sendError("Your account is not active", 403);
}

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON", 400);
}

$receiver_id = (int)($input['receiver_id'] ?? 0);
$title       = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');

/* VALIDATION */
if (!$receiver_id) {
    sendError("receiver_id required", 422);
}

if ($receiver_id === $initiator_id) {
    sendError("Cannot create deal with yourself", 422);
}

/* CHECK RECEIVER ACTIVE */
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'ACTIVE'");
$stmt->execute([$receiver_id]);

if (!$stmt->fetch()) {
    sendError("Receiver not active", 404);
}

/* GET INITIATOR NAME */
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$initiator_id]);

$initiator = $stmt->fetch(PDO::FETCH_ASSOC);
$initiator_name = $initiator['name'] ?? 'Vendor';

/* PREVENT DUPLICATE */
$stmt = $pdo->prepare("
    SELECT id FROM vendor_deals
    WHERE 
    (
        (initiator_id = ? AND receiver_id = ?)
        OR 
        (initiator_id = ? AND receiver_id = ?)
    )
    AND status IN ('PENDING','ACCEPTED')
");

$stmt->execute([
    $initiator_id,
    $receiver_id,
    $receiver_id,
    $initiator_id
]);

if ($stmt->fetch()) {
    sendError("Active deal already exists", 409);
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* CREATE DEAL */
    $stmt = $pdo->prepare("
        INSERT INTO vendor_deals 
        (initiator_id, receiver_id, title, description, status)
        VALUES (?, ?, ?, ?, 'PENDING')
    ");

    $stmt->execute([
        $initiator_id,
        $receiver_id,
        $title ?: null,
        $description ?: null
    ]);

    $deal_id = (int)$pdo->lastInsertId();

    /* CREATE NOTIFICATION ✅ FIXED */
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'DEAL_INVITE', ?, ?)
    ");

    $stmt->execute([
        $receiver_id,          // receiver
        $initiator_id,         // actor (who sent)
        $deal_id,
        "{$initiator_name} sent you a deal request"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError($e->getMessage(), 500); // DEBUG MODE 🔥
}

/* RESPONSE */
sendSuccess("Deal created & invitation sent", [
    "deal_id" => $deal_id,
    "status" => "PENDING"
]);