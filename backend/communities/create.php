
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

$name        = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$type        = strtoupper(trim($input['type'] ?? 'BUSINESS'));

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if ($name === '') {
    sendError("Community name is required", 422, "VALIDATION_ERROR");
}

if (strlen($name) < 3) {
    sendError("Community name must be at least 3 characters", 422, "VALIDATION_ERROR");
}

if (!in_array($type, ['BUSINESS', 'EVENT'])) {
    sendError("Invalid community type", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   DB TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* ---------------------------------------------
       CREATE COMMUNITY
    --------------------------------------------- */
    $stmt = $pdo->prepare("
        INSERT INTO communities (name, description, created_by, type, status)
        VALUES (?, ?, ?, ?, 'ACTIVE')
    ");

    $stmt->execute([
        $name,
        $description ?: null,
        $user_id,
        $type
    ]);

    $community_id = (int)$pdo->lastInsertId();

    /* ---------------------------------------------
       ADD CREATOR AS ADMIN
    --------------------------------------------- */
    $memberStmt = $pdo->prepare("
        INSERT INTO community_members (community_id, user_id, role)
        VALUES (?, ?, 'ADMIN')
    ");

    $memberStmt->execute([
        $community_id,
        $user_id
    ]);

    

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();

    // Debug (optional - remove in production)
    // error_log($e->getMessage());

    sendError("Failed to create community", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Community created successfully",
    [
        "community" => [
            "id"          => $community_id,
            "name"        => $name,
            "description" => $description,
            "type"        => $type,
            "status"      => "ACTIVE",
            "created_by"  => $user_id
        ]
    ]
);