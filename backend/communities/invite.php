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
   AUTH (JWT)
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

$community_id     = (int)($input['community_id'] ?? 0);
$invited_user_id  = (int)($input['invited_user_id'] ?? 0);

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if (!$community_id || !$invited_user_id) {
    sendError("community_id and invited_user_id required", 422, "VALIDATION_ERROR");
}

if ($user_id === $invited_user_id) {
    sendError("You cannot invite yourself", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   CHECK COMMUNITY EXISTS + GET NAME
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, name 
    FROM communities 
    WHERE id = ? AND status = 'ACTIVE'
");
$stmt->execute([$community_id]);

$community = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$community) {
    sendError("Community not found", 404, "NOT_FOUND");
}

/* -------------------------------------------------
   CHECK ADMIN PERMISSION
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM community_members 
    WHERE community_id = ? AND user_id = ? AND role = 'ADMIN'
");
$stmt->execute([$community_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Only admin can invite users", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   CHECK USER EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$invited_user_id]);

if (!$stmt->fetch()) {
    sendError("User not found", 404, "NOT_FOUND");
}

/* -------------------------------------------------
   CHECK ALREADY MEMBER
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM community_members 
    WHERE community_id = ? AND user_id = ?
");
$stmt->execute([$community_id, $invited_user_id]);

if ($stmt->fetch()) {
    sendError("User already a member", 409, "ALREADY_MEMBER");
}

/* -------------------------------------------------
   CHECK EXISTING PENDING INVITE
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM community_invites 
    WHERE community_id = ? AND invited_user_id = ? AND status = 'PENDING'
");
$stmt->execute([$community_id, $invited_user_id]);

if ($stmt->fetch()) {
    sendError("Invite already sent", 409, "INVITE_EXISTS");
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* INSERT INVITE */
    $stmt = $pdo->prepare("
        INSERT INTO community_invites 
        (community_id, invited_by, invited_user_id, status)
        VALUES (?, ?, ?, 'PENDING')
    ");

    $stmt->execute([
        $community_id,
        $user_id,
        $invited_user_id
    ]);

    $invite_id = (int)$pdo->lastInsertId();

    /* GET INVITER NAME */
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    $inviter = $stmt->fetch(PDO::FETCH_ASSOC);
    $inviter_name = $inviter['name'] ?? 'Admin';

    /* 🔔 NOTIFICATION */
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'COMMUNITY_INVITE', ?, ?)
    ");

    $stmt->execute([
        $invited_user_id,     // receiver
        $user_id,             // actor
        $invite_id, 
        "{$inviter_name} invited you to join {$community['name']}"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError($e->getMessage(), 500); // debug mode
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Invite sent successfully",
    [
        "invite" => [
            "id" => $invite_id,
            "community_id" => $community_id,
            "invited_user_id" => $invited_user_id,
            "status" => "PENDING"
        ]
    ]
);