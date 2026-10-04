<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* -------------------------------------------------
   PREFLIGHT
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   AUTH : ADMIN OR BUSINESS
------------------------------------------------- */
require_auth();
$authUser = $GLOBALS['auth_user'];

$user_one = (int)$authUser['id'];
$role     = $authUser['role'];

if (!in_array($role, ['admin', 'business'])) {
    sendError("Access denied", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);
$user_two = (int)($input['user_id'] ?? 0);

if ($user_two <= 0) {
    sendError("user_id is required", 400, "USER_ID_REQUIRED");
}

if ($user_two === $user_one) {
    sendError("Cannot start chat with yourself", 400, "INVALID_USER");
}

/* -------------------------------------------------
   FETCH TARGET USER
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, role, status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$user_two]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    sendError("User not found", 404, "USER_NOT_FOUND");
}

if ($target['status'] !== 'ACTIVE') {
    sendError("User is not active", 403, "USER_INACTIVE");
}

/* -------------------------------------------------
   CHAT RULES
   ✔ Admin ↔ Business only
------------------------------------------------- */
if ($role === 'admin' && $target['role'] !== 'business') {
    sendError(
        "Admin can chat only with business users",
        403,
        "INVALID_CHAT_ROLE"
    );
}

if ($role === 'business' && $target['role'] !== 'admin') {
    sendError(
        "Business can chat only with admin",
        403,
        "INVALID_CHAT_ROLE"
    );
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

/* -------------------------------------------------
   BUSINESS PROFILE (FOR UI)
------------------------------------------------- */
$businessUserId = ($role === 'admin') ? $user_two : $user_one;

$bpStmt = $pdo->prepare("
    SELECT id
    FROM business_profiles
    WHERE user_id = ?
    LIMIT 1
");
$bpStmt->execute([$businessUserId]);
$businessProfile = $bpStmt->fetch(PDO::FETCH_ASSOC);

if ($chat) {
    sendSuccess("Chat already exists", [
        "chat_id"             => (int)$chat['id'],
        "business_profile_id" => $businessProfile['id'] ?? null
    ]);
}

/* -------------------------------------------------
   CREATE CHAT
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    // Create chat
    $stmt = $pdo->prepare("
        INSERT INTO chats
            (user_one, user_two, created_by, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user_one, $user_two, $user_one]);

    $chat_id = (int)$pdo->lastInsertId();

    // Admin activity meta
    $stmt = $pdo->prepare("
        INSERT INTO chat_activity_log
            (chat_id, business_one, business_two, message_count, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([
        $chat_id,
        $businessUserId,
        ($role === 'admin') ? $user_one : $user_two
    ]);

    $pdo->commit();

    sendSuccess("Admin chat started successfully", [
        "chat_id"             => $chat_id,
        "business_profile_id" => $businessProfile['id'] ?? null,
        "admin_id"            => ($role === 'admin') ? $user_one : $user_two
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();

    sendError(
        "Failed to start admin chat",
        500,
        "SERVER_ERROR"
    );
}
