<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
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
   AUTH : ADMIN OR BUSINESS
------------------------------------------------- */
require_auth();
$authUser = $GLOBALS['auth_user'];

$user_id = (int)$authUser['id'];
$role    = $authUser['role'];

if (!in_array($role, ['admin', 'business'])) {
    sendError("Access denied", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   INPUT (JSON)
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);
$post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;

if ($post_id <= 0) {
    sendError("post_id is required", 400, "POST_ID_REQUIRED");
}

/* -------------------------------------------------
   FETCH POST
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, user_id, status
    FROM posts
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    sendError("Post not found", 404, "POST_NOT_FOUND");
}

/* -------------------------------------------------
   PERMISSION CHECK
------------------------------------------------- */
if ($role === 'business' && (int)$post['user_id'] !== $user_id) {
    sendError(
        "You are not allowed to delete this post",
        403,
        "NOT_POST_OWNER"
    );
}

if ($post['status'] === 'DELETED') {
    sendError(
        "Post already deleted",
        409,
        "POST_ALREADY_DELETED"
    );
}

/* -------------------------------------------------
   SOFT DELETE
------------------------------------------------- */
$stmt = $pdo->prepare("
    UPDATE posts
    SET status = 'DELETED',
        updated_at = NOW()
    WHERE id = ?
");
$stmt->execute([$post_id]);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Post deleted successfully",
    [
        "post_id" => $post_id,
        "deleted_by" => $role
    ]
);
