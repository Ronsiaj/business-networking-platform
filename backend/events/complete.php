<?php
header("Content-Type: application/json; charset=utf-8");
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
   METHOD CHECK
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405);
}

/* -------------------------------------------------
   AUTH
------------------------------------------------- */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$user_id = (int)$user['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON input", 400);
}

$event_id = (int)($input['event_id'] ?? 0);

if (!$event_id) {
    sendError("event_id is required", 422);
}

/* -------------------------------------------------
   FETCH EVENT
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id, client_id, status, event_date FROM events WHERE id = ?");
$stmt->execute([$event_id]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    sendError("Event not found", 404);
}

/* -------------------------------------------------
   ACCESS CONTROL
------------------------------------------------- */
if ((int)$event['client_id'] !== $user_id) {
    sendError("You are not allowed to complete this event", 403);
}

/* -------------------------------------------------
   BUSINESS VALIDATIONS
------------------------------------------------- */

/* Already completed */
if ($event['status'] === 'COMPLETED') {
    sendError("Event already completed", 409);
}

/* Cannot complete before event date */
if ($event['event_date'] > date('Y-m-d')) {
    sendError("Event cannot be completed before event date", 422);
}

/* Optional: only allow from ONGOING */
if (!in_array($event['status'], ['ONGOING'])) {
    sendError("Only ongoing events can be completed", 422);
}

/* -------------------------------------------------
   UPDATE STATUS
------------------------------------------------- */
try {

    $stmt = $pdo->prepare("
        UPDATE events 
        SET status = 'COMPLETED'
        WHERE id = ?
    ");

    $stmt->execute([$event_id]);

} catch (Exception $e) {
    sendError("Failed to complete event", 500);
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Event marked as completed", [
    "event_id" => $event_id,
    "status"   => "COMPLETED"
]);