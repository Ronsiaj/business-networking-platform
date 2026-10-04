<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* ✅ STRICT IST TIME (NO DEPENDENCY) */
$istTime = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))
            ->format('Y-m-d H:i:s');

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
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

/* AUTH */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$user_id = (int)$user['id'];

/* -------------------------------------------------
   DETECT REQUEST TYPE
------------------------------------------------- */
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isFormData = strpos($contentType, 'multipart/form-data') !== false;

/* -------------------------------------------------
   INPUT HANDLING
------------------------------------------------- */
if ($isFormData) {

    $community_id = (int)($_POST['community_id'] ?? 0);
    $message_type = 'IMAGE';

} else {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        sendError("Invalid JSON input", 400, "INVALID_JSON");
    }

    $community_id = (int)($input['community_id'] ?? 0);
    $message      = trim($input['message'] ?? '');
    $message_type = strtoupper(trim($input['message_type'] ?? 'TEXT'));
}

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if (!$community_id) {
    sendError("community_id required", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   CHECK COMMUNITY
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id FROM communities WHERE id = ? AND status = 'ACTIVE'");
$stmt->execute([$community_id]);

if (!$stmt->fetch()) {
    sendError("Community not found", 404);
}

/* -------------------------------------------------
   CHECK MEMBER
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM community_members 
    WHERE community_id = ? AND user_id = ?
");
$stmt->execute([$community_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("You are not a member", 403);
}

/* -------------------------------------------------
   HANDLE IMAGE / TEXT
------------------------------------------------- */
if ($isFormData) {

    if (!isset($_FILES['file'])) {
        sendError("Image file required", 422);
    }

    $file = $_FILES['file'];

    if ($file['error'] !== 0) {
        sendError("File upload error", 500);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

    if (!in_array($file['type'], $allowed)) {
        sendError("Invalid image type", 422);
    }

    $uploadDir = __DIR__ . '/../../uploads/community_chat/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'img_' . time() . '_' . rand(1000,9999) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        sendError("Failed to upload image", 500);
    }

    $message = 'uploads/community_chat/' . $fileName;

} else {

    if ($message === '') {
        sendError("Message cannot be empty", 422);
    }

    if (!in_array($message_type, ['TEXT'])) {
        sendError("Invalid message type", 422);
    }
}

/* -------------------------------------------------
   INSERT MESSAGE (FORCED IST TIME)
------------------------------------------------- */
$stmt = $pdo->prepare("
    INSERT INTO community_messages 
    (community_id, sender_id, message, message_type, created_at)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $community_id,
    $user_id,
    $message,
    $message_type,
    $istTime   // ✅ ALWAYS IST
]);

$message_id = (int)$pdo->lastInsertId();

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Message sent successfully", [
    "message" => [
        "id" => $message_id,
        "community_id" => $community_id,
        "sender_id" => $user_id,
        "message" => $message,
        "message_type" => $message_type,
        "created_at" => $istTime   // ✅ ALWAYS IST
    ]
]);