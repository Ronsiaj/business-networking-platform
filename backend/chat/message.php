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
require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$chat_id = (int)($_POST['chat_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$type    = strtoupper($_POST['type'] ?? 'TEXT'); // TEXT / IMAGE / FILE

if (!$chat_id) {
    sendError("chat_id required", 422);
}

/* -------------------------------------------------
   VERIFY CHAT ACCESS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM chats
    WHERE id = ?
    AND (user_one = ? OR user_two = ?)
");
$stmt->execute([$chat_id, $user_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Access denied", 403);
}

/* -------------------------------------------------
   VALIDATE TYPE
------------------------------------------------- */
$allowedTypes = ['TEXT', 'IMAGE', 'FILE'];

if (!in_array($type, $allowedTypes)) {
    sendError("Invalid message type", 422);
}

/* -------------------------------------------------
   FILE HANDLING
------------------------------------------------- */
$file_url = null;
$file_name = null;
$file_size = null;

if ($type === 'IMAGE' || $type === 'FILE') {

    if (!isset($_FILES['file'])) {
        sendError("File required", 422);
    }

    $file = $_FILES['file'];

    if ($file['error'] !== 0) {
        sendError("File upload error", 400);
    }

    /* SIZE CHECK (5MB) */
    if ($file['size'] > 5 * 1024 * 1024) {
        sendError("File size exceeds 5MB", 422);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    /* TYPE VALIDATION */
    if ($type === 'IMAGE') {
        $allowedExt = ['jpg','jpeg','png','webp'];
    } else {
        $allowedExt = ['pdf','doc','docx','xls','xlsx','zip','txt'];
    }

    if (!in_array($ext, $allowedExt)) {
        sendError("Invalid file type", 422);
    }

    /* UPLOAD PATH */
    $uploadDir = __DIR__ . '/../uploads/chat/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newName = uniqid() . '.' . $ext;
    $filePath = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        sendError("Failed to upload file", 500);
    }

    $file_url  = 'uploads/chat/' . $newName;
    $file_name = $file['name'];
    $file_size = $file['size'];

    /* IMAGE STORE */
    if ($type === 'IMAGE') {
        $message = $file_url;
    }
}

/* -------------------------------------------------
   TEXT VALIDATION
------------------------------------------------- */
if ($type === 'TEXT' && $message === '') {
    sendError("Message cannot be empty", 422);
}

/* -------------------------------------------------
   INSERT MESSAGE
------------------------------------------------- */
try {

    $stmt = $pdo->prepare("
        INSERT INTO chat_messages
        (chat_id, sender_id, message, message_type, file_url, file_name, file_size)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $chat_id,
        $user_id,
        $message ?: null,
        $type,
        $file_url,
        $file_name,
        $file_size
    ]);

    $message_id = (int)$pdo->lastInsertId();

} catch (Exception $e) {
    sendError("Failed to send message", 500);
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Message sent successfully", [
    "message_id" => $message_id,
    "chat_id" => $chat_id,
    "type" => $type,
    "message" => $message,
    "file_url" => $file_url
]);