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
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$initiator_id = (int)$user['id'];

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON", 400);
}

$community_id   = (int)($input['community_id'] ?? 0);
$remove_user_id = (int)($input['user_id'] ?? 0);

/* VALIDATION */
if ($community_id <= 0 || $remove_user_id <= 0) {
    sendError("community_id and user_id required", 422);
}

/* -------------------------------------------------
   PROCESS
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* COMMUNITY CHECK */
    $stmt = $pdo->prepare("
        SELECT id, name, created_by, status 
        FROM communities 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$community_id]);

    $community = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$community) {
        $pdo->rollBack();
        sendError("Community not found", 404);
    }

    if ($community['status'] !== 'ACTIVE') {
        $pdo->rollBack();
        sendError("Community is not active", 400);
    }

    /* ONLY CREATOR */
    if ((int)$community['created_by'] !== $initiator_id) {
        $pdo->rollBack();
        sendError("Only creator can remove members", 403);
    }

    /* PREVENT SELF REMOVE */
    if ($remove_user_id === (int)$community['created_by']) {
        $pdo->rollBack();
        sendError("Cannot remove community creator", 400);
    }

    /* MEMBER CHECK */
    $stmt = $pdo->prepare("
        SELECT id FROM community_members 
        WHERE community_id = ? AND user_id = ?
    ");
    $stmt->execute([$community_id, $remove_user_id]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();
        sendError("User not in community", 404);
    }

    /* REMOVE USER */
    $stmt = $pdo->prepare("
        DELETE FROM community_members 
        WHERE community_id = ? AND user_id = ?
    ");
    $stmt->execute([$community_id, $remove_user_id]);

    /* GET CREATOR NAME */
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$initiator_id]);

    $creator = $stmt->fetch(PDO::FETCH_ASSOC);
    $creator_name = $creator['name'] ?? 'Admin';

    /* 🔔 NOTIFICATION (LIKE DEAL LOGIC) */
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'COMMUNITY_REMOVED', ?, ?)
    ");

    $stmt->execute([
        $remove_user_id,              // receiver
        $initiator_id,               // actor
        $community_id,
        "{$creator_name} removed you from {$community['name']}"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError($e->getMessage(), 500); // debug mode
}

/* RESPONSE */
sendSuccess("User removed successfully", [
    "community_id" => $community_id,
    "removed_user_id" => $remove_user_id
]);