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

$meeting_id = (int)($input['meeting_id'] ?? 0);
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$meeting_date = $input['meeting_date'] ?? '';
$reminder_days = (int)($input['reminder_days_before'] ?? 1);

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if ($meeting_id <= 0) {
    sendError("meeting_id is required", 422, "VALIDATION_ERROR");
}

if ($title === '' || strlen($title) < 3) {
    sendError("Title must be at least 3 characters", 422, "VALIDATION_ERROR");
}

if (!$meeting_date || strtotime($meeting_date) === false) {
    sendError("Invalid meeting_date", 422, "VALIDATION_ERROR");
}

/* Prevent past update */
if (strtotime($meeting_date) <= time()) {
    sendError("Meeting must be a future date", 422, "INVALID_DATE");
}

/* Reminder validation */
if ($reminder_days < 0 || $reminder_days > 7) {
    sendError("Reminder days must be between 0 and 7", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   FETCH & OWNERSHIP CHECK
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, user_id, status 
    FROM user_meetings 
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$meeting_id]);

$meeting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
    sendError("Meeting not found", 404, "NOT_FOUND");
}

/* Ownership check */
if ((int)$meeting['user_id'] !== $user_id) {
    sendError("You can update only your own meetings", 403, "FORBIDDEN");
}

/* Prevent update if completed/cancelled */
if (in_array($meeting['status'], ['COMPLETED','CANCELLED'])) {
    sendError("Cannot update completed or cancelled meeting", 409, "INVALID_STATE");
}

/* -------------------------------------------------
   UPDATE
------------------------------------------------- */
try {

    $stmt = $pdo->prepare("
        UPDATE user_meetings 
        SET title = ?, 
            description = ?, 
            meeting_date = ?, 
            reminder_days_before = ?, 
            reminder_sent = 0,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $title,
        $description ?: null,
        $meeting_date,
        $reminder_days,
        $meeting_id
    ]);

} catch (Exception $e) {
    sendError("Failed to update meeting", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Meeting updated successfully", [
    "meeting_id" => $meeting_id,
    "updated" => true
]);