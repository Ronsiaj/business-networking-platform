<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

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
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */

require_business();
$authUser = $GLOBALS['auth_user'];
$user_id  = (int)$authUser['id'];

/* -------------------------------------------------
   PAGINATION
------------------------------------------------- */
$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

/* -------------------------------------------------
   FETCH OWN POSTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.user_id,
        p.content,
        p.post_type,
        p.status,
        p.created_at,
        bp.business_name,
        bp.logo
    FROM posts p
    JOIN business_profiles bp ON bp.user_id = p.user_id
    WHERE p.user_id = ?
      AND p.status = 'ACTIVE'
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $limit, $offset]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$posts) {
    sendSuccess("Own posts fetched successfully", [
        "limit" => $limit,
        "offset" => $offset,
        "count" => 0,
        "list" => []
    ]);
}

/* -------------------------------------------------
   POST IDS
------------------------------------------------- */
$postIds = array_column($posts, 'id');
$in = implode(',', array_fill(0, count($postIds), '?'));

/* -------------------------------------------------
   MEDIA
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT post_id, media_url, media_type
    FROM post_media
    WHERE post_id IN ($in)
");
$stmt->execute($postIds);
$mediaRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mediaMap = [];
foreach ($mediaRows as $m) {
    $mediaMap[$m['post_id']][] = [
        "url"  => $m['media_url'],
        "type" => $m['media_type']
    ];
}

/* -------------------------------------------------
   LIKES
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        pr.post_id,
        bp.id AS business_profile_id,
        bp.business_name,
        bp.logo
    FROM post_reactions pr
    JOIN users u ON u.id = pr.user_id
    LEFT JOIN business_profiles bp ON bp.user_id = u.id
    WHERE pr.post_id IN ($in)
");
$stmt->execute($postIds);
$likeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$likeMap = [];
foreach ($likeRows as $l) {
    $likeMap[$l['post_id']][] = [
        "business_profile_id" => $l['business_profile_id']
            ? (int)$l['business_profile_id']
            : null,
        "business_name" => $l['business_name'],
        "logo" => $l['logo'] ?? null
    ];
}

/* -------------------------------------------------
   COMMENTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        pc.id,
        pc.post_id,
        pc.comment,
        pc.created_at,
        bp.id AS business_profile_id,
        bp.business_name,
        bp.logo
    FROM post_comments pc
    JOIN users u ON u.id = pc.user_id
    LEFT JOIN business_profiles bp ON bp.user_id = u.id
    WHERE pc.post_id IN ($in)
    ORDER BY pc.created_at ASC
");
$stmt->execute($postIds);
$commentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$commentMap = [];
foreach ($commentRows as $c) {
    $commentMap[$c['post_id']][] = [
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
   MERGE EVERYTHING
------------------------------------------------- */
foreach ($posts as &$p) {

    $p['media'] = $mediaMap[$p['id']] ?? [];

    $p['like_count'] = isset($likeMap[$p['id']])
        ? count($likeMap[$p['id']])
        : 0;

    $p['liked_by'] = $likeMap[$p['id']] ?? [];

    $p['comment_count'] = isset($commentMap[$p['id']])
        ? count($commentMap[$p['id']])
        : 0;

    $p['comments'] = $commentMap[$p['id']] ?? [];
}
unset($p);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess(
    "Own posts fetched successfully",
    [
        "limit" => $limit,
        "offset" => $offset,
        "count" => count($posts),
        "list" => $posts
    ]
);