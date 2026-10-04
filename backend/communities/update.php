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
$name         = trim($input['name'] ?? '');
$description  = trim($input['description'] ?? '');
$type         = strtoupper(trim($input['type'] ?? ''));

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */
if ($community_id <= 0) {
    sendError("Valid community_id is required", 422, "VALIDATION_ERROR");
}

if ($name === '') {
    sendError("Community name is required", 422, "VALIDATION_ERROR");
}

if (strlen($name) < 3) {
    sendError("Community name must be at least 3 characters", 422, "VALIDATION_ERROR");
}

if ($type !== '' && !in_array($type, ['BUSINESS', 'EVENT'])) {
    sendError("Invalid community type", 422, "VALIDATION_ERROR");
}

/* -------------------------------------------------
   PROCESS
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* ---------------------------------------------
       FETCH COMMUNITY
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
       CHECK OWNER ACCESS
    --------------------------------------------- */
    if ((int)$community['created_by'] !== $user_id) {
        $pdo->rollBack();
        sendError("Only creator can update this community", 403, "FORBIDDEN");
    }

    /* ---------------------------------------------
       PREPARE UPDATE FIELDS
    --------------------------------------------- */
    $updateFields = [];
    $params = [];

    // name (mandatory)
    $updateFields[] = "name = ?";
    $params[] = $name;

    // description (optional)
    $updateFields[] = "description = ?";
    $params[] = ($description !== '') ? $description : null;

    // type (optional)
    if ($type !== '') {
        $updateFields[] = "type = ?";
        $params[] = $type;
    }

    $params[] = $community_id;

    /* ---------------------------------------------
       UPDATE QUERY
    --------------------------------------------- */
    $sql = "UPDATE communities SET " . implode(", ", $updateFields) . " WHERE id = ?";

    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();

    // error_log($e->getMessage());

    sendError("Failed to update community", 500, "SERVER_ERROR");
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Community updated successfully",
    [
        "community_id" => $community_id,
        "name"         => $name,
        "description"  => $description,
        "type"         => $type ?: "UNCHANGED"
    ]
);