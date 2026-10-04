<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/db.php';
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
   INPUT
------------------------------------------------- */
$data = json_decode(file_get_contents("php://input"), true);

$name   = trim($data['name'] ?? '');
$email  = strtolower(trim($data['email'] ?? ''));
$mobile = trim($data['mobile'] ?? '');

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
$errors = [];

if (!$name || strlen($name) < 3) {
    $errors[] = "Name must be at least 3 characters";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
    $errors[] = "Invalid mobile number";
}

if (!empty($errors)) {
    sendError(implode(", ", $errors), 400, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   DUPLICATE CHECK
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id FROM users 
    WHERE email = ? OR mobile = ?
    LIMIT 1
");
$stmt->execute([$email, $mobile]);

if ($stmt->fetch()) {
    sendError(
        "Email or mobile already registered",
        409,
        "DUPLICATE_ENTRY"
    );
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    /* CREATE USER */
    $stmt = $pdo->prepare("
        INSERT INTO users 
        (role, name, email, mobile, status, email_verified, created_at)
        VALUES 
        ('business', ?, ?, ?, 'INVITED', 0, NOW())
    ");
    $stmt->execute([$name, $email, $mobile]);

    $user_id = (int)$pdo->lastInsertId();

    /* GENERATE TOKEN */
    $token = bin2hex(random_bytes(32));

    /* STORE TOKEN */
    $stmt = $pdo->prepare("
        INSERT INTO user_invites 
        (user_id, token, expires_at, created_at)
        VALUES 
        (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
    ");
    $stmt->execute([$user_id, $token]);

    /* -------------------------------------------------
       SEND VERIFICATION MAIL
    ------------------------------------------------- */

    $verificationLink = "http://localhost:3000/set-password/" . $token;
    // production: https://yourdomain.com/set-password/$token

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'contact@nirubanguna.com';
    $mail->Password   = 'Niruban1712@@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('contact@nirubanguna.com', 'Business Platform');
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Verify Your Account';

    $mail->Body = "
        <p>Hello <strong>{$name}</strong>,</p>
        <p>Thanks for registering.</p>
        <p>Click below to verify your email and set your password:</p>
        <p>
            <a href='{$verificationLink}'
               style='padding:10px 15px;background:#000;color:#fff;text-decoration:none'>
               Verify & Set Password
            </a>
        </p>
        <p>This link expires in 24 hours.</p>
    ";

    /* SEND MAIL */
    if (!$mail->send()) {
        throw new Exception("Mail Error: " . $mail->ErrorInfo);
    }

    $pdo->commit();

    sendSuccess("Registration successful. Verification email sent", [
        "user_id" => $user_id,
        "email"   => $email
    ]);

} catch (Throwable $e) {

    $pdo->rollBack();

    sendError(
        "Registration failed: " . $e->getMessage(),
        500,
        "SERVER_ERROR"
    );
}