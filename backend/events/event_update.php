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

$user_id   = (int)$user['id'];
$user_role = $user['role'] ?? '';

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON input", 400);
}

$event_id   = (int)($input['event_id'] ?? 0);
$title      = isset($input['title']) ? trim($input['title']) : null;
$description= isset($input['description']) ? trim($input['description']) : null;
$event_date = $input['event_date'] ?? null;
$status     = isset($input['status']) ? strtoupper(trim($input['status'])) : null;

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if (!$event_id) {
    sendError("event_id required", 422);
}

/* CHECK EVENT EXISTS */
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    sendError("Event not found", 404);
}

/* ACCESS CHECK */
if ((int)$event['client_id'] !== $user_id) {
    sendError("Unauthorized to update this event", 403);
}

/* BUILD UPDATE FIELDS */
$fields = [];
$params = [];

/* TITLE */
if ($title !== null) {

    if ($title === '') {
        sendError("Title cannot be empty", 422);
    }

    if (strlen($title) < 3) {
        sendError("Title must be at least 3 characters", 422);
    }

    if (strlen($title) > 255) {
        sendError("Title too long", 422);
    }

    $fields[] = "title = ?";
    $params[] = $title;
}

/* DESCRIPTION */
if ($description !== null) {
    $fields[] = "description = ?";
    $params[] = $description ?: null;
}

/* EVENT DATE */
if ($event_date !== null) {

    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $event_date)) {
        sendError("Invalid date format (YYYY-MM-DD)", 422);
    }

    if (!strtotime($event_date)) {
        sendError("Invalid date", 422);
    }

    if ($event_date < date('Y-m-d')) {
        sendError("Event date cannot be in past", 422);
    }

    $fields[] = "event_date = ?";
    $params[] = $event_date;
}

/* STATUS */
if ($status !== null) {

    if (!in_array($status, ['PLANNING','ONGOING','COMPLETED'])) {
        sendError("Invalid status", 422);
    }

    $fields[] = "status = ?";
    $params[] = $status;
}

/* NOTHING TO UPDATE */
if (empty($fields)) {
    sendError("No fields to update", 422);
}

/* -------------------------------------------------
   UPDATE QUERY
------------------------------------------------- */
$params[] = $event_id;

try {

    $stmt = $pdo->prepare("
        UPDATE events 
        SET " . implode(', ', $fields) . "
        WHERE id = ?
    ");

    $stmt->execute($params);

} catch (Exception $e) {
    sendError("Failed to update event", 500);
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Event updated successfully", [
    "event_id" => $event_id,
    "updated_fields" => array_map(function($f){
        return explode(' =', $f)[0];
    }, $fields)
]);