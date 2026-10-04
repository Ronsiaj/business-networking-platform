<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   HEADERS
------------------------------------------------- */

// Allow requests from any origin (change * to specific domain in production)
header("Access-Control-Allow-Origin: *");

// Allow specific HTTP methods
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// Allow specific headers
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight (OPTIONS request)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Response type
header("Content-Type: application/json");

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);
$chat_id = (int)($input['chat_id'] ?? 0);

if ($chat_id <= 0) {
    sendError("chat_id is required", 400, "CHAT_ID_REQUIRED");
}

/* -------------------------------------------------
   FETCH TYPING USER (LAST 3 SECONDS)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT ct.user_id, bp.id AS business_profile_id, bp.business_name
    FROM chat_typing ct
    JOIN business_profiles bp ON bp.user_id = ct.user_id
    WHERE ct.chat_id = ?
      AND ct.user_id != ?
      AND ct.updated_at > (NOW() - INTERVAL 3 SECOND)
    LIMIT 1
");
$stmt->execute([$chat_id, $user_id]);

$typing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($typing) {
    sendSuccess("Typing status", [
        "typing" => true,
        "business_profile_id" => (int)$typing['business_profile_id'],
        "business_name" => $typing['business_name']
    ]);
}

sendSuccess("Typing status", [
    "typing" => false
]);