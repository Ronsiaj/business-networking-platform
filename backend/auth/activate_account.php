<?php
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
   INPUT
------------------------------------------------- */
$data = json_decode(file_get_contents("php://input"), true);

$token    = trim($data['token'] ?? '');
$password = $data['password'] ?? '';

if (!$token || !$password) {
    sendError("Token and password required", 400, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   PASSWORD VALIDATION
------------------------------------------------- */
if (strlen($password) < 8) {
    sendError(
        "Password must be at least 8 characters",
        400,
        "WEAK_PASSWORD"
    );
}

/* -------------------------------------------------
   VALIDATE INVITE TOKEN
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT ui.user_id, u.status
    FROM user_invites ui
    JOIN users u ON u.id = ui.user_id
    WHERE ui.token = ?
      AND ui.used_at IS NULL
      AND ui.expires_at > NOW()
    LIMIT 1
");
$stmt->execute([$token]);
$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    sendError(
        "Invalid or expired activation link",
        400,
        "INVALID_TOKEN"
    );
}

/* -------------------------------------------------
   CHECK USER STATUS
------------------------------------------------- */
if ($invite['status'] === 'ACTIVE') {
    sendError(
        "Account already activated",
        400,
        "ALREADY_ACTIVATED"
    );
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    /* HASH PASSWORD */
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    /* ACTIVATE USER */
    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?,
            status = 'ACTIVE',
            email_verified = 1,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$hashedPassword, $invite['user_id']]);

    /* MARK INVITE USED */
    $stmt = $pdo->prepare("
        UPDATE user_invites
        SET used_at = NOW()
        WHERE token = ?
    ");
    $stmt->execute([$token]);

    $pdo->commit();

    sendSuccess("Account activated successfully");

} catch (Throwable $e) {
    $pdo->rollBack();

    sendError(
        "Failed to activate account",
        500,
        "SERVER_ERROR"
    );
}
