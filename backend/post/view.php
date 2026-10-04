<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405);
}

/* -------------------------------------------------
   AUTH : ADMIN OR BUSINESS
------------------------------------------------- */
require_auth();
$authUser = $GLOBALS['auth_user'];

$role    = $authUser['role'];
$user_id = (int)$authUser['id'];

if (!in_array($role, ['admin', 'business'], true)) {
    sendError("Access denied", 403);
}

/* -------------------------------------------------
   POST ID
------------------------------------------------- */
$post_id = (int)($_GET['post_id'] ?? 0);
if ($post_id <= 0) {
    sendError("post_id is required", 400);
}

/* -------------------------------------------------
   FETCH POST
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.user_id,
        p.content,
        p.post_type,
        p.created_at,
        bp.business_name,
        bp.logo
    FROM posts p
    LEFT JOIN business_profiles bp
        ON bp.user_id = p.user_id
    WHERE p.id = ?
      AND p.status = 'ACTIVE'
    LIMIT 1
");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    sendError("Post not found", 404);
}

/* -------------------------------------------------
   MEDIA
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT media_url, media_type
    FROM post_media
    WHERE post_id = ?
");
$stmt->execute([$post_id]);
$mediaRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$post['media'] = [];
foreach ($mediaRows as $m) {
    $post['media'][] = [
        "url"  => $m['media_url'],
        "type" => $m['media_type']
    ];
}

/* -------------------------------------------------
   LIKES (WITH LOGO)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        bp.id AS business_profile_id,
        bp.logo,
        IF(u.role='admin','admin',bp.business_name) AS business_name
    FROM post_reactions pr
    JOIN users u ON u.id = pr.user_id
    LEFT JOIN business_profiles bp ON bp.user_id = u.id
    WHERE pr.post_id = ?
");
$stmt->execute([$post_id]);
$likeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$post['like_count'] = count($likeRows);
$post['liked_by'] = [];

foreach ($likeRows as $l) {
    $post['liked_by'][] = [
        "business_profile_id" => $l['business_profile_id']
            ? (int)$l['business_profile_id']
            : null,
        "business_name" => $l['business_name'],
        "logo" => $l['logo'] ?? null
    ];
}

/* -------------------------------------------------
   COMMENTS (WITH LOGO)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        pc.id,
        pc.comment,
        pc.created_at,
        bp.id AS business_profile_id,
        bp.logo,
        IF(u.role='admin','admin',bp.business_name) AS business_name
    FROM post_comments pc
    JOIN users u ON u.id = pc.user_id
    LEFT JOIN business_profiles bp ON bp.user_id = u.id
    WHERE pc.post_id = ?
    ORDER BY pc.created_at ASC
");
$stmt->execute([$post_id]);
$commentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$post['comment_count'] = count($commentRows);
$post['comments'] = [];

foreach ($commentRows as $c) {
    $post['comments'][] = [
        "comment_id" => (int)$c['id'],
        "comment" => $c['comment'],
        "commented_by" => [
            "business_profile_id" => $c['business_profile_id']
                ? (int)$c['business_profile_id']
                : null,
            "business_name" => $c['business_name'],
            "logo" => $c['logo'] ?? null
        ],
        "created_at" => $c['created_at']
    ];
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Post fetched successfully",
    [
        "viewer_role" => $role,
        "post" => $post
    ]
);