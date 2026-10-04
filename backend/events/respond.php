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
    sendError("Invalid JSON input", 400, "INVALID_JSON");
}

$event_vendor_id = (int)($input['event_vendor_id'] ?? 0);
$status = strtoupper(trim($input['status'] ?? ''));

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if (!$event_vendor_id) {
    sendError("event_vendor_id required", 422, "VALIDATION_ERROR");
}

if (!in_array($status, ['ACCEPTED', 'REJECTED'])) {
    sendError("Status must be ACCEPTED or REJECTED", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   CHECK INVITE EXISTS + OWNERSHIP
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, status, event_id 
    FROM event_vendors
    WHERE id = ? AND vendor_id = ?
");
$stmt->execute([$event_vendor_id, $user_id]);

$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    sendError("Invite not found or unauthorized", 404, "NOT_FOUND");
}

/* Prevent re-response */
if ($invite['status'] !== 'INVITED') {
    sendError("Already responded", 409, "ALREADY_RESPONDED");
}

/* -------------------------------------------------
   CHECK EVENT STATUS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT status FROM events WHERE id = ?
");
$stmt->execute([$invite['event_id']]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    sendError("Event not found", 404, "NOT_FOUND");
}

if ($event['status'] !== 'ONGOING') {
    sendError("Event not active", 409, "INVALID_STATE");
}

/* -------------------------------------------------
   UPDATE STATUS
------------------------------------------------- */
$stmt = $pdo->prepare("
    UPDATE event_vendors
    SET status = ?
    WHERE id = ?
");

$stmt->execute([$status, $event_vendor_id]);

/* -------------------------------------------------
   OPTIONAL: NOTIFICATION TO CLIENT
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT client_id FROM events WHERE id = ?
");
$stmt->execute([$invite['event_id']]);

$eventData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($eventData) {
    $message = ($status === 'ACCEPTED')
        ? "Vendor accepted your event request"
        : "Vendor rejected your event request";

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $eventData['client_id'],
        $user_id,
        'event_vendor_response',
        $event_vendor_id,
        $message
    ]);
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Response updated successfully", [
    "event_vendor_id" => $event_vendor_id,
    "status" => $status
]);