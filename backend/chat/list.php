<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405);
}

require_business();
$authUserId = (int)$GLOBALS['auth_user']['id'];

$stmt = $pdo->prepare("
    SELECT c.id, c.user_one, c.user_two
    FROM chats c
    WHERE (c.user_one = ? AND c.deleted_by_one = 0)
       OR (c.user_two = ? AND c.deleted_by_two = 0)
    ORDER BY (
        SELECT MAX(cm.created_at) 
        FROM chat_messages cm 
        WHERE cm.chat_id = c.id
    ) DESC
");
$stmt->execute([$authUserId, $authUserId]);
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$chats) {
    sendSuccess("Chat list fetched", ["count" => 0, "list" => []]);
}

$otherIds = [];
foreach ($chats as $c) {
    $otherIds[] = ($c['user_one'] == $authUserId)
        ? $c['user_two']
        : $c['user_one'];
}
$otherIds = array_unique($otherIds);
$in = implode(',', array_fill(0, count($otherIds), '?'));

$stmt = $pdo->prepare("
    SELECT id, user_id, business_name, logo, is_verified
    FROM business_profiles
    WHERE user_id IN ($in)
");
$stmt->execute($otherIds);
$businessProfiles = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $businessProfiles[$b['user_id']] = $b;
}

$stmt = $pdo->prepare("
    SELECT id, name
    FROM users
    WHERE role = 'admin'
      AND id IN ($in)
");
$stmt->execute($otherIds);
$admins = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $admins[$a['id']] = $a;
}

$list = [];
foreach ($chats as $c) {
    $chatId  = (int)$c['id'];
    $otherId = ($c['user_one'] == $authUserId)
        ? (int)$c['user_two']
        : (int)$c['user_one'];

    if (isset($businessProfiles[$otherId])) {
        $otherUser = [
            "type"                => "business",
            "user_id"             => $otherId,                                    // ✅ ADDED — needed by last_seen.php
            "business_profile_id" => (int)$businessProfiles[$otherId]['id'],
            "name"                => $businessProfiles[$otherId]['business_name'],
            "logo"                => $businessProfiles[$otherId]['logo'],
            "is_verified"         => (int)$businessProfiles[$otherId]['is_verified'] === 1
        ];

        $stmt = $pdo->prepare("
            SELECT message, created_at
            FROM chat_messages
            WHERE chat_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$chatId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastMessage   = $msg['message']    ?? null;
        $lastMessageAt = $msg['created_at'] ?? null;

    } elseif (isset($admins[$otherId])) {
        $otherUser = [
            "type"        => "admin",
            "user_id"     => (int)$admins[$otherId]['id'],                        // ✅ consistent key
            "name"        => $admins[$otherId]['name'],
            "is_verified" => false
        ];

        $stmt = $pdo->prepare("
            SELECT message, created_at
            FROM admin_messages
            WHERE business_user_id = ?
              AND admin_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$authUserId, $otherId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastMessage   = $msg['message']    ?? null;
        $lastMessageAt = $msg['created_at'] ?? null;

    } else {
        continue;
    }

    $list[] = [
        "chat_id"         => $chatId,
        "other_user"      => $otherUser,
        "last_message"    => $lastMessage,
        "last_message_at" => $lastMessageAt
    ];
}

sendSuccess(
    "Chat list fetched successfully",
    [
        "count" => count($list),
        "list"  => $list
    ]
);