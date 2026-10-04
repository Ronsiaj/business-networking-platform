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
   AUTH : ADMIN ONLY
------------------------------------------------- */
require_auth();
$authUser = $GLOBALS['auth_user'];
$role = $authUser['role'];

if ($role !== 'admin') {
    sendError("Access denied. Only admin can delete comments", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   INPUT (JSON)
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);
$comment_id = isset($input['comment_id']) ? (int) $input['comment_id'] : 0;

if ($comment_id <= 0) {
    sendError("comment_id is required and must be a positive integer", 400, "COMMENT_ID_REQUIRED");
}

/* -------------------------------------------------
   CHECK COMMENT EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id, post_id, user_id
    FROM post_comments
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$comment_id]);
$comment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$comment) {
    sendError("Comment not found", 404, "COMMENT_NOT_FOUND");
}

/* -------------------------------------------------
   DELETE COMMENT
------------------------------------------------- */
$stmt = $pdo->prepare("DELETE FROM post_comments WHERE id = ?");
$stmt->execute([$comment_id]);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Comment deleted successfully",
    [
        "comment_id" => $comment_id,
        "post_id" => $comment['post_id']
    ]
);