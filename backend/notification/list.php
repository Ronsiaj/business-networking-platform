<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* AUTH */
require_auth();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* FETCH NOTIFICATIONS */
$stmt = $pdo->prepare("
    SELECT
        n.id,
        n.type,
        n.message,
        n.reference_id,
        n.is_read,
        n.created_at,

        u.role AS actor_role,

        bp.id AS business_profile_id,
        bp.business_name,
        bp.logo,

        ci.id AS invite_id,
        ci.status AS invite_status

    FROM notifications n
    LEFT JOIN users u ON u.id = n.actor_user_id
    LEFT JOIN business_profiles bp ON bp.user_id = n.actor_user_id
    LEFT JOIN community_invites ci
        ON ci.id = n.reference_id
        AND ci.invited_user_id = ?
        AND n.type = 'COMMUNITY_INVITE'
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   COLLECT IDs FOR PRELOADING
------------------------------------------------- */
$postIds       = [];
$dealIds       = [];
$dealInviteIds = [];

foreach ($rows as $r) {
    if ($r['type'] === 'post_comment' && $r['reference_id']) {
        $postIds[] = (int)$r['reference_id'];
    }
    if ($r['type'] === 'DEAL_MESSAGE' && $r['reference_id']) {
        $dealIds[] = (int)$r['reference_id'];
    }
    if ($r['type'] === 'DEAL_INVITE' && $r['reference_id']) {
        $dealInviteIds[] = (int)$r['reference_id'];
    }
}

/* COMMENT MAP */
$commentMap = [];
if ($postIds) {
    $postIds = array_values(array_unique($postIds)); // 👈 array_values
    $in = implode(',', array_fill(0, count($postIds), '?'));
    $stmt = $pdo->prepare("
        SELECT post_id, comment
        FROM post_comments
        WHERE post_id IN ($in)
        ORDER BY created_at DESC
    ");
    $stmt->execute($postIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if (!isset($commentMap[$c['post_id']])) {
            $commentMap[$c['post_id']] = $c['comment'];
        }
    }
}

/* DEAL MESSAGE MAP */
$dealMessageMap = [];
if ($dealIds) {
    $dealIds = array_values(array_unique($dealIds)); // 👈 array_values
    $in = implode(',', array_fill(0, count($dealIds), '?'));
    $stmt = $pdo->prepare("
        SELECT deal_id, message
        FROM vendor_deal_messages
        WHERE deal_id IN ($in)
        ORDER BY created_at DESC
    ");
    $stmt->execute($dealIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        if (!isset($dealMessageMap[$d['deal_id']])) {
            $dealMessageMap[$d['deal_id']] = $d['message'];
        }
    }
}

/* DEAL STATUS MAP */
$dealStatusMap = [];
if ($dealInviteIds) {
    $dealInviteIds = array_values(array_unique($dealInviteIds)); // 👈 array_values
    $in = implode(',', array_fill(0, count($dealInviteIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, status FROM vendor_deals WHERE id IN ($in)
    ");
    $stmt->execute($dealInviteIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $dealStatusMap[$d['id']] = $d['status'];
    }
}

/* -------------------------------------------------
   FORMAT RESPONSE
------------------------------------------------- */
$list = [];

foreach ($rows as $r) {

    /* FROM OBJECT */
    if ($r['actor_role'] === 'business') {
        $from = [
            "role" => "business",
            "profile" => [
                "business_profile_id" => $r['business_profile_id'] ? (int)$r['business_profile_id'] : null,
                "business_name"       => $r['business_name'],
                "logo"                => $r['logo']
            ]
        ];
    } elseif ($r['actor_role'] === 'admin') {
        $from = [
            "role" => "admin",
            "profile" => [
                "name" => "Platform Admin",
                "logo" => null
            ]
        ];
    } else {
        $from = null;
    }

    /* TYPE BASED */
    $post_id      = null;
    $chat_id      = null;
    $comment_text = null;
    $deal_message = null;

    if (in_array($r['type'], ['post_like', 'post_comment'], true)) {
        $post_id = $r['reference_id'] ? (int)$r['reference_id'] : null;
        if ($r['type'] === 'post_comment' && $post_id) {
            $comment_text = $commentMap[$post_id] ?? null;
        }
    }

    if (in_array($r['type'], ['chat_message', 'admin_message'], true)) {
        $chat_id = $r['reference_id'] ? (int)$r['reference_id'] : null;
    }

    if ($r['type'] === 'DEAL_MESSAGE') {
        $deal_message = $dealMessageMap[$r['reference_id']] ?? null;
    }

    $list[] = [
        "id"            => (int)$r['id'],
        "type"          => $r['type'],
        "message"       => $r['message'],
        "reference_id"  => $r['reference_id'] ? (int)$r['reference_id'] : null,
        "invite_id"     => $r['invite_id'] ? (int)$r['invite_id'] : null,
        "invite_status" => $r['type'] === 'COMMUNITY_INVITE'          // ← ADD
                            ? ($r['invite_status'] ?? 'PENDING')
                            : null,
        "deal_status"   => $r['type'] === 'DEAL_INVITE'               // ← ADD
                            ? ($dealStatusMap[$r['reference_id']] ?? 'PENDING')
                            : null,
        "from"          => $from,
        "post_id"       => $post_id,
        "chat_id"       => $chat_id,
        "comment"       => $comment_text,
        "deal_message"  => $deal_message,
        "is_read"       => (bool)$r['is_read'],
        "created_at"    => $r['created_at']
    ];
}

/* RESPONSE */
sendSuccess("Notifications fetched successfully", [
    "count" => count($list),
    "list"  => $list
]);