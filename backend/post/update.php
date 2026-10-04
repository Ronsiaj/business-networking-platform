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
    sendError(
        "Method not allowed. Use POST",
        405,
        "METHOD_NOT_ALLOWED"
    );
}

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT (JSON)
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

$post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;
$content = isset($input['content']) ? strtolower(trim($input['content'])) : '';

if ($post_id <= 0) {
    sendError(
        "post_id is required",
        400,
        "POST_ID_REQUIRED"
    );
}

if ($content === '') {
    sendError(
        "content is required",
        400,
        "CONTENT_REQUIRED"
    );
}

/* -------------------------------------------------
   FETCH POST (OWN POST ONLY)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, status
    FROM posts
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");
$stmt->execute([$post_id, $user_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    sendError(
        "Post not found or not owned by you",
        404,
        "POST_NOT_FOUND"
    );
}

if ($post['status'] === 'DELETED') {
    sendError(
        "Cannot update deleted post",
        409,
        "POST_DELETED"
    );
}

/* -------------------------------------------------
   UPDATE POST (BUSINESS OWNERSHIP)
------------------------------------------------- */
$stmt = $pdo->prepare("
    UPDATE posts
    SET content = ?,
        updated_at = NOW()
    WHERE id = ?
");
$stmt->execute([$content, $post_id]);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Post updated successfully",
    [
        "post_id" => $post_id
    ]
);
