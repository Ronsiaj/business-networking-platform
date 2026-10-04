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

if ($meeting_id <= 0) {
    sendError("Valid meeting_id is required", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   FETCH + OWNERSHIP CHECK
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

/* Only creator can delete */
if ((int)$meeting['user_id'] !== $user_id) {
    sendError("You can delete only your own meetings", 403, "FORBIDDEN");
}

/* Optional: Prevent deleting completed meetings */
if ($meeting['status'] === 'COMPLETED') {
    sendError("Cannot delete completed meeting", 409, "INVALID_STATE");
}

/* -------------------------------------------------
   DELETE
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        DELETE FROM user_meetings 
        WHERE id = ?
    ");
    $stmt->execute([$meeting_id]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Failed to delete meeting", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Meeting deleted successfully", [
    "meeting_id" => $meeting_id
]);