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

$event_id  = (int)($input['event_id'] ?? 0);
$deal_id   = (int)($input['deal_id'] ?? 0);
$vendor_id = (int)($input['vendor_id'] ?? 0);
$rating    = (int)($input['rating'] ?? 0);
$review    = trim($input['review'] ?? '');

/* VALIDATION */
if (!$vendor_id) {
    sendError("vendor_id required", 422);
}

if ($rating < 1 || $rating > 5) {
    sendError("Rating must be between 1-5", 422);
}

if (!$event_id && !$deal_id) {
    sendError("event_id or deal_id required", 422);
}

/* -------------------------------------------------
   CHECK VENDOR EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$vendor_id]);

if (!$stmt->fetch()) {
    sendError("Vendor not found", 404);
}

/* -------------------------------------------------
   EVENT FLOW
------------------------------------------------- */
if ($event_id) {

    $stmt = $pdo->prepare("
        SELECT client_id, status 
        FROM events 
        WHERE id = ?
    ");
    $stmt->execute([$event_id]);

    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) sendError("Event not found", 404);
    if ($event['client_id'] != $user_id) sendError("Only event owner can review", 403);
    if ($event['status'] !== 'COMPLETED') sendError("Event not completed", 422);

    $stmt = $pdo->prepare("
        SELECT id FROM event_vendors
        WHERE event_id = ? AND vendor_id = ?
    ");
    $stmt->execute([$event_id, $vendor_id]);

    if (!$stmt->fetch()) {
        sendError("Vendor not part of this event", 403);
    }
}

/* -------------------------------------------------
   DEAL FLOW
------------------------------------------------- */
if ($deal_id) {

    $stmt = $pdo->prepare("
        SELECT initiator_id, receiver_id, status
        FROM vendor_deals
        WHERE id = ?
    ");
    $stmt->execute([$deal_id]);

    $deal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deal) sendError("Deal not found", 404);
    if ($deal['initiator_id'] != $user_id) sendError("Only deal creator can review", 403);
    if ($deal['status'] !== 'COMPLETED') sendError("Deal not completed", 422);
    if ($deal['receiver_id'] != $vendor_id) sendError("Invalid vendor for this deal", 422);
}

/* -------------------------------------------------
   PREVENT DUPLICATE REVIEW
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM reviews
    WHERE reviewer_id = ? 
    AND vendor_id = ?
    AND (event_id = ? OR deal_id = ?)
");

$stmt->execute([
    $user_id,
    $vendor_id,
    $event_id ?: null,
    $deal_id ?: null
]);

if ($stmt->fetch()) {
    sendError("You already reviewed this vendor", 409);
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* INSERT REVIEW */
    $stmt = $pdo->prepare("
        INSERT INTO reviews
        (event_id, deal_id, reviewer_id, vendor_id, rating, review)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $event_id ?: null,
        $deal_id ?: null,
        $user_id,
        $vendor_id,
        $rating,
        $review ?: null
    ]);

    $review_id = (int)$pdo->lastInsertId();

    /* GET REVIEWER NAME */
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    $reviewer = $stmt->fetch(PDO::FETCH_ASSOC);
    $reviewer_name = $reviewer['name'] ?? 'User';

    /* 🔔 NOTIFICATION TO VENDOR */
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'VENDOR_REVIEW', ?, ?)
    ");

    $stmt->execute([
        $vendor_id,             // receiver
        $user_id,               // actor
        $review_id,
        "{$reviewer_name} gave you a {$rating}⭐ review"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError($e->getMessage(), 500); // debug mode
}

/* RESPONSE */
sendSuccess("Review submitted", [
    "review_id" => $review_id,
    "rating" => $rating
]);