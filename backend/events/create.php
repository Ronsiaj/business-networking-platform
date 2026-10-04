<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:3000");
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
   AUTH (ALL USERS CAN CREATE EVENT)
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

$title       = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$event_date  = $input['event_date'] ?? null;

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */

// Title
if ($title === '') {
    sendError("Title is required", 422, "VALIDATION_ERROR");
}

if (strlen($title) < 3) {
    sendError("Title must be at least 3 characters", 422, "VALIDATION_ERROR");
}

if (strlen($title) > 255) {
    sendError("Title too long (max 255)", 422, "VALIDATION_ERROR");
}

// Event Date
if (!$event_date) {
    sendError("event_date is required", 422, "VALIDATION_ERROR");
}

// Validate date format (YYYY-MM-DD)
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $event_date)) {
    sendError("Invalid date format (use YYYY-MM-DD)", 422, "VALIDATION_ERROR");
}

// Check valid date
$checkDate = strtotime($event_date);
if (!$checkDate) {
    sendError("Invalid event date", 422, "VALIDATION_ERROR");
}

// Prevent past date
if ($event_date < date('Y-m-d')) {
    sendError("Event date cannot be in the past", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   DB INSERT
------------------------------------------------- */
try {

    $stmt = $pdo->prepare("
        INSERT INTO events (client_id, title, description, event_date, status)
        VALUES (?, ?, ?, ?, 'PLANNING')
    ");

    $stmt->execute([
        $user_id,
        $title,
        $description ?: null,
        $event_date
    ]);

    $event_id = (int)$pdo->lastInsertId();

} catch (Exception $e) {
    sendError("Failed to create event", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Event created successfully",
    [
        "event" => [
            "id" => $event_id,
            "title" => $title,
            "description" => $description,
            "event_date" => $event_date,
            "status" => "PLANNING",
            "client_id" => $user_id
        ]
    ]
);