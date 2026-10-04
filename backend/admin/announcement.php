<?php


header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


/* HANDLE PREFLIGHT REQUEST */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   AUTH : ADMIN ONLY
------------------------------------------------- */
require_admin();
$admin_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    sendError("message is required", 400, "MESSAGE_REQUIRED");
}

/* -------------------------------------------------
   FETCH ALL ACTIVE BUSINESS USERS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE role = 'business'
      AND status = 'ACTIVE'
");
$stmt->execute();

$businessUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$businessUsers) {
    sendError("No active business users found", 404, "NO_BUSINESS_USERS");
}

/* -------------------------------------------------
   INSERT NOTIFICATIONS (TRANSACTION)
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare("
        INSERT INTO notifications
            (user_id, actor_user_id, type, message, reference_id, is_read, created_at)
        VALUES
            (?, ?, 'admin_announcement', ?, NULL, 0, NOW())
    ");

    foreach ($businessUsers as $b) {
        $insert->execute([
            (int)$b['id'],   // receiver (business)
            $admin_id,       // actor (admin)
            $message
        ]);
    }

    $pdo->commit();

    sendSuccess("Announcement sent successfully", [
        "sent_to" => count($businessUsers),
        "message" => $message
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();

    sendError(
        "Failed to send announcement",
        500,
        "SERVER_ERROR"
    );
}
