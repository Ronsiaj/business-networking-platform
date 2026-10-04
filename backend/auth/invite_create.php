<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


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
   ADMIN AUTH (IMPORTANT)
   - Do NOT assign to variable
   - This itself blocks or allows
------------------------------------------------- */

require_admin();

/* -------------------------------------------------
   INPUT
------------------------------------------------- */

$input = json_decode(file_get_contents("php://input"), true);

$business_name = strtolower(trim($input['business_name'] ?? ''));
$email         = strtolower(trim($input['email'] ?? ''));
$mobile        = strtolower(trim($input['mobile'] ?? ''));

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */

$missing = [];
if (!$business_name) $missing[] = "business_name";
if (!$email)         $missing[] = "email";
if (!$mobile)        $missing[] = "mobile";

if (!empty($missing)) {
    sendError(
        "Missing fields: " . implode(", ", $missing),
        400,
        "VALIDATION_ERROR"
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError("Invalid email format", 400, "INVALID_EMAIL");
}

if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
    sendError("Invalid mobile number", 400, "INVALID_MOBILE");
}

/* -------------------------------------------------
   DUPLICATE CHECK
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ? OR mobile = ?
    LIMIT 1
");
$stmt->execute([$email, $mobile]);

if ($stmt->fetch()) {
    sendError(
        "Email or mobile already exists",
        409,
        "DUPLICATE_ENTRY"
    );
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */

try {
    $pdo->beginTransaction();

    /* INSERT USER */
    $stmt = $pdo->prepare("
        INSERT INTO users (role, name, email, mobile, status, created_at)
        VALUES ('business', ?, ?, ?, 'INVITED', NOW())
    ");
    $stmt->execute([$business_name, $email, $mobile]);

    $user_id = (int) $pdo->lastInsertId();

    /* GENERATE TOKEN */
    $token = bin2hex(random_bytes(32));

    /* INSERT INVITE */
    $stmt = $pdo->prepare("
        INSERT INTO user_invites (user_id, token, expires_at, created_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
    ");
    $stmt->execute([$user_id, $token]);

    /* -------------------------------------------------
       SEND MAIL – DIRECT SMTP
    ------------------------------------------------- */
    
    $activationLink = "http://localhost:3000/set-password/" . $token;
    // $activationLink = "https://qnexus.in/set-password/" . $token;


    $mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host       = 'smtp.hostinger.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'contact@nirubanguna.com';
$mail->Password   = 'Niruban1712@@';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Port       = 465;
$mail->Timeout    = 20;

$mail->setFrom('contact@nirubanguna.com', 'Business Platform');
$mail->addAddress($email);

$mail->isHTML(true);
$mail->Subject = 'Business Account Invitation';
 $mail->Body = "
        <p>Hello <strong>{$business_name}</strong>,</p>
        <p>You are invited to create your business account.</p>
        <p>
            <a href='{$activationLink}'
               style='padding:10px 15px;background:#000;color:#fff;text-decoration:none'>
               Activate Account
            </a>
        </p>
        <p>This link is valid for 24 hours.</p>
    ";
    

    $mail->SMTPDebug  = 2;
    $mail->Debugoutput = 'error_log';


    $mail->send();

    $pdo->commit();

    sendSuccess("Invitation sent successfully", [
        "user_id" => $user_id,
        "email"   => $email
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();

    sendError(
        "Failed to send invitation",
        500,
        "SERVER_ERROR"
    );
}
