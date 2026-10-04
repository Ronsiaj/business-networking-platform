<?php
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

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

$email    = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (!$email || !$password) {
    sendError("Email and password required", 400, "VALIDATION_ERROR");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError("Invalid email format", 400, "INVALID_EMAIL");
}

/* -------------------------------------------------
   FETCH USER + BUSINESS PROFILE
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.role,
        u.name,
        u.email,
        u.mobile,
        u.password,
        u.status,
        bp.id AS business_profile_id,
        bp.profile_completion
    FROM users u
    LEFT JOIN business_profiles bp ON bp.user_id = u.id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    sendError("Invalid credentials", 401, "INVALID_CREDENTIALS");
}

/* -------------------------------------------------
   PASSWORD VERIFY
------------------------------------------------- */
if (!password_verify($password, $user['password'])) {
    sendError("Invalid credentials", 401, "INVALID_CREDENTIALS");
}

/* -------------------------------------------------
   STATUS CHECK
------------------------------------------------- */
if ($user['status'] === 'INVITED') {
    sendError(
        "Account not activated. Please activate using invite email",
        403,
        "ACCOUNT_NOT_ACTIVATED"
    );
}

if ($user['status'] === 'BLOCKED') {
    sendError(
        "Account blocked. Contact administrator",
        403,
        "ACCOUNT_BLOCKED"
    );
}

if ($user['status'] !== 'ACTIVE') {
    sendError(
        "Account not active",
        403,
        "ACCOUNT_INACTIVE"
    );
}

/* -------------------------------------------------
   JWT GENERATE
------------------------------------------------- */
$token = jwt_generate_token([
    'id'   => $user['id'],
    'role' => $user['role']
]);

/* -------------------------------------------------
   PROFILE COMPLETION LOGIC
------------------------------------------------- */
$profileCompletion = (int) ($user['profile_completion'] ?? 0);
$profileCompleted  = ($profileCompletion === 100);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Login successful", [
    "token" => $token,
    "profile_completed" => $profileCompleted,
    "profile_completion" => $profileCompletion,
    "user" => [
        "id" => (int)$user['id'],
        "business_profile_id" => $user['business_profile_id']
            ? (int)$user['business_profile_id']
            : null,
        "role"   => $user['role'],
        "name"   => $user['name'],
        "email"  => $user['email'],
        "mobile" => $user['mobile'],
        "status" => $user['status']
    ]
]);