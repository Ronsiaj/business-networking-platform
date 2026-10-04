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

if ($post_id <= 0) {
    sendError("post_id is required", 400, "POST_ID_REQUIRED");
}

/* -------------------------------------------------
   CHECK POST EXISTS & ACTIVE
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT p.id, bp.category_id, bp.sub_category_id
    FROM posts p
    JOIN business_profiles bp ON bp.user_id = p.user_id
    WHERE p.id = ?
      AND p.status = 'ACTIVE'
    LIMIT 1
");
$stmt->execute([$post_id]);
$postMeta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$postMeta) {
    sendError("Post not found", 404, "POST_NOT_FOUND");
}

/* -------------------------------------------------
   CHECK EXISTING REACTION
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT id
    FROM post_reactions
    WHERE post_id = ? AND user_id = ?
    LIMIT 1
");
$stmt->execute([$post_id, $user_id]);
$existingReaction = $stmt->fetch(PDO::FETCH_ASSOC);


/* fetch post owner */
$stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id=?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if ($post && $post['user_id'] != $user_id) {
    createNotification(
        $pdo,
        $post['user_id'],        // receiver
        $user_id,                // actor
        'post_like',
        $post_id,
        'liked your post'
    );
}

/* -------------------------------------------------
   TOGGLE REACTION
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    if ($existingReaction) {
        /* UNLIKE */
        $stmt = $pdo->prepare("
            DELETE FROM post_reactions
            WHERE post_id = ? AND user_id = ?
        ");
        $stmt->execute([$post_id, $user_id]);

        $pdo->commit();

        sendSuccess("Reaction removed", [
            "post_id" => $post_id,
            "liked"   => false,
            "by"      => $role
        ]);
    }

    /* LIKE */
    $stmt = $pdo->prepare("
        INSERT INTO post_reactions (post_id, user_id, created_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$post_id, $user_id]);

    /* UPDATE INTEREST MAP (OPTIONAL FOR ADMIN, STILL OK) */
    $stmt = $pdo->prepare("
        INSERT INTO user_interest_map
            (user_id, category_id, sub_category_id, score)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE score = score + 1
    ");
    $stmt->execute([
        $user_id,
        (int)$postMeta['category_id'],
        (int)$postMeta['sub_category_id']
    ]);

    $pdo->commit();

    sendSuccess("Post liked", [
        "post_id" => $post_id,
        "liked"   => true,
        "by"      => $role
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();

    sendError(
        "Failed to toggle reaction",
        500,
        "SERVER_ERROR"
    );
}
