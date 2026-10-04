<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once '../helpers/notification.php';


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

$role    = $authUser['role'];
$user_id = (int)$authUser['id'];

if (!in_array($role, ['admin', 'business'])) {
    sendError("Access denied", 403, "FORBIDDEN");
}

/* -------------------------------------------------
   INPUT (JSON)
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

$post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;
$comment = isset($input['comment']) ? strtolower(trim($input['comment'])) : '';

if ($post_id <= 0) {
    sendError("post_id is required", 400, "POST_ID_REQUIRED");
}

if ($comment === '') {
    sendError("comment is required", 400, "COMMENT_REQUIRED");
}

/* -------------------------------------------------
   CHECK POST EXISTS & ACTIVE
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id
    FROM posts
    WHERE id = ?
      AND status = 'ACTIVE'
    LIMIT 1
");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    sendError("Post not found", 404, "POST_NOT_FOUND");
}

/* -------------------------------------------------
   INSERT COMMENT
------------------------------------------------- */
$stmt = $pdo->prepare("
    INSERT INTO post_comments
        (post_id, user_id, comment, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$post_id, $user_id, $comment]);

$comment_id = (int)$pdo->lastInsertId();

//notification table insert// 

$stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id=?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if ($post && $post['user_id'] != $user_id) {
    createNotification(
        $pdo,
        $post['user_id'],
        $user_id,
        'post_comment',
        $post_id,
        'commented on your post'
    );
}

/* -------------------------------------------------
   RESOLVE COMMENTED BY NAME
------------------------------------------------- */
$commentedBy = 'admin';

if ($role === 'business') {
    $stmt = $pdo->prepare("
        SELECT business_name
        FROM business_profiles
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $bp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bp && !empty($bp['business_name'])) {
        $commentedBy = $bp['business_name'];
    }
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Comment added successfully",
    [
        "comment_id"   => $comment_id,
        "post_id"      => $post_id,
        "comment"      => $comment,
        "commented_by" => $commentedBy
    ]
);
