<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';


/* -------------------------------------------------
   PREFLIGHT
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$authUser = $GLOBALS['auth_user'];

$user_one = (int)$authUser['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

$user_two = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if ($user_two <= 0) {
    sendError("user_id is required", 400, "USER_ID_REQUIRED");
}

if ($user_two === $user_one) {
    sendError("Cannot start chat with yourself", 400, "INVALID_USER");
}

/* -------------------------------------------------
   VALIDATE TARGET USER
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, role, status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$user_two]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    sendError("User not found", 404, "USER_NOT_FOUND");
}

if ($targetUser['role'] !== 'business') {
    sendError("Chat allowed only with business users", 403, "INVALID_CHAT_USER");
}

if ($targetUser['status'] !== 'ACTIVE') {
    sendError("User is not active", 403, "USER_INACTIVE");
}

/* -------------------------------------------------
   FETCH BUSINESS PROFILES (BOTH SIDES)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, user_id, business_name
    FROM business_profiles
    WHERE user_id IN (?, ?)
");
$stmt->execute([$user_one, $user_two]);
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$profileMap = [];
foreach ($profiles as $p) {
    $profileMap[$p['user_id']] = [
        "business_profile_id" => (int)$p['id'],
        "business_name" => $p['business_name']
    ];
}

if (!isset($profileMap[$user_one]) || !isset($profileMap[$user_two])) {
    sendError("Business profile missing", 404, "BUSINESS_PROFILE_NOT_FOUND");
}

/* -------------------------------------------------
   CHECK EXISTING CHAT
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id
    FROM chats
    WHERE (user_one = ? AND user_two = ?)
       OR (user_one = ? AND user_two = ?)
    LIMIT 1
");
$stmt->execute([
    $user_one, $user_two,
    $user_two, $user_one
]);
$chat = $stmt->fetch(PDO::FETCH_ASSOC);

// After: $chat = $stmt->fetch(PDO::FETCH_ASSOC);
if ($chat) {
    // ✅ Reset deleted flags when chat is reopened
    $pdo->prepare("
        UPDATE chats
        SET deleted_by_one = CASE WHEN user_one = ? THEN 0 ELSE deleted_by_one END,
            deleted_by_two = CASE WHEN user_two = ? THEN 0 ELSE deleted_by_two END
        WHERE id = ?
    ")->execute([$user_one, $user_one, (int)$chat['id']]);

    sendSuccess("Chat already exists", [
        "chat_id"        => (int)$chat['id'],
        "my_business"    => $profileMap[$user_one],
        "other_business" => $profileMap[$user_two]
    ]);
}
/* -------------------------------------------------
   CREATE CHAT
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO chats
            (user_one, user_two, created_by, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user_one, $user_two, $user_one]);

    $chat_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO chat_activity_log
            (chat_id, business_one, business_two, message_count, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$chat_id, $user_one, $user_two]);

    $pdo->commit();

    sendSuccess(
        "Chat started successfully",
        [
            "chat_id" => $chat_id,
            "my_business" => $profileMap[$user_one],
            "other_business" => $profileMap[$user_two]
        ]
    );

} catch (Throwable $e) {
    $pdo->rollBack();
    sendError("Failed to start chat", 500, "SERVER_ERROR");
}
