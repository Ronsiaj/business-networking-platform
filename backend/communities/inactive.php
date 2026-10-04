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

$community_id = (int)($input['community_id'] ?? 0);

if ($community_id <= 0) {
    sendError("Valid community_id is required", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   PROCESS
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* ---------------------------------------------
       CHECK COMMUNITY EXISTS
    --------------------------------------------- */
    $stmt = $pdo->prepare("
        SELECT id, created_by, status 
        FROM communities 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$community_id]);

    $community = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$community) {
        $pdo->rollBack();
        sendError("Community not found", 404, "NOT_FOUND");
    }

    /* ---------------------------------------------
       ALREADY INACTIVE?
    --------------------------------------------- */
    if ($community['status'] === 'INACTIVE') {
        $pdo->rollBack();
        sendError("Community already inactive", 400, "ALREADY_INACTIVE");
    }

    /* ---------------------------------------------
       CHECK PERMISSION
       (CREATOR OR ADMIN)
    --------------------------------------------- */
    $isCreator = ((int)$community['created_by'] === $user_id);

    $memberStmt = $pdo->prepare("
        SELECT role 
        FROM community_members 
        WHERE community_id = ? AND user_id = ?
        LIMIT 1
    ");
    $memberStmt->execute([$community_id, $user_id]);

    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

    $isAdmin = ($member && $member['role'] === 'ADMIN');

    if (!$isCreator && !$isAdmin) {
        $pdo->rollBack();
        sendError("You don't have permission to deactivate this community", 403, "FORBIDDEN");
    }

    /* ---------------------------------------------
       UPDATE STATUS
    --------------------------------------------- */
    $updateStmt = $pdo->prepare("
        UPDATE communities 
        SET status = 'INACTIVE' 
        WHERE id = ?
    ");
    $updateStmt->execute([$community_id]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();

    // error_log($e->getMessage()); // optional

    sendError("Failed to deactivate community", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Community deactivated successfully",
    [
        "community_id" => $community_id,
        "status"       => "INACTIVE"
    ]
);