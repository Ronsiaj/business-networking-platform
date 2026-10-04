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

$user_id = (int)$user['id'];

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

$invite_id = (int)($input['invite_id'] ?? 0);
$status = strtoupper($input['status'] ?? '');

if (!$invite_id || !in_array($status, ['ACCEPTED', 'REJECTED'])) {
    sendError("Invalid input", 422);
}

/* GET INVITE */
$stmt = $pdo->prepare("
    SELECT * FROM community_invites 
    WHERE id = ? AND invited_user_id = ?
");
$stmt->execute([$invite_id, $user_id]);

$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    sendError("Invite not found", 404);
}

if ($invite['status'] !== 'PENDING') {
    sendError("Invite already responded", 409);
}

/* TRANSACTION */
try {

    $pdo->beginTransaction();

    /* UPDATE STATUS */
    $stmt = $pdo->prepare("
        UPDATE community_invites 
        SET status = ?, responded_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $invite_id]);

    /* ACCEPT → ADD MEMBER */
    if ($status === 'ACCEPTED') {

        // avoid duplicate
        $check = $pdo->prepare("
            SELECT id FROM community_members 
            WHERE community_id = ? AND user_id = ?
        ");
        $check->execute([$invite['community_id'], $user_id]);

        if (!$check->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO community_members (community_id, user_id, role)
                VALUES (?, ?, 'MEMBER')
            ");
            $stmt->execute([
                $invite['community_id'],
                $user_id
            ]);
        }
    }

    /* 🔔 NOTIFICATION (TO ADMIN WHO INVITED) */
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, ?, ?, ?)
    ");

    $message = ($status === 'ACCEPTED')
        ? "User accepted your community invite"
        : "User rejected your community invite";

    $stmt->execute([
        $invite['invited_by'], // notify admin
        $user_id,
        'community_invite_response',
        $invite_id,
        $message
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError("Something went wrong", 500);
}

/* RESPONSE */
sendSuccess("Invite {$status} successfully");