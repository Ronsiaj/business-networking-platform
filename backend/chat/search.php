<?php
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

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$search = strtolower(trim($_GET['search'] ?? ''));
$type   = strtolower(trim($_GET['type'] ?? 'all'));
$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));

if ($search === '') {
    sendError("search keyword is required", 400, "SEARCH_REQUIRED");
}

if (!in_array($type, ['chat', 'message', 'all'])) {
    sendError("Invalid search type", 400, "INVALID_TYPE");
}

$like = '%' . $search . '%';

/* -------------------------------------------------
   BUILD SEARCH CONDITION
------------------------------------------------- */
$conditions = [];
$params = [];

/* Business name search */
if ($type === 'chat' || $type === 'all') {
    $conditions[] = "LOWER(bp.business_name) LIKE ?";
    $params[] = $like;
}

/* Message search */
if ($type === 'message' || $type === 'all') {
    $conditions[] = "LOWER(cm.message) LIKE ?";
    $params[] = $like;
}

$whereSearch = implode(' OR ', $conditions);

/* -------------------------------------------------
   MAIN SEARCH QUERY
------------------------------------------------- */
$sql = "
SELECT DISTINCT
    c.id AS chat_id,
    bp.id AS business_profile_id,
    bp.business_name,
    bp.logo,

    /* last message */
    (
        SELECT m.message
        FROM chat_messages m
        WHERE m.chat_id = c.id
          AND m.deleted_for_everyone = 0
        ORDER BY m.created_at DESC
        LIMIT 1
    ) AS last_message,

    (
        SELECT m.created_at
        FROM chat_messages m
        WHERE m.chat_id = c.id
        ORDER BY m.created_at DESC
        LIMIT 1
    ) AS last_message_at,

    /* matched message for highlight */
    cm.id AS matched_message_id,
    cm.message AS matched_message,
    cm.created_at AS matched_message_at

FROM chats c
JOIN business_profiles bp
  ON bp.user_id = IF(c.user_one = ?, c.user_two, c.user_one)
LEFT JOIN chat_messages cm
  ON cm.chat_id = c.id
 AND cm.deleted_for_everyone = 0

WHERE (c.user_one = ? OR c.user_two = ?)
  AND ($whereSearch)

ORDER BY matched_message_at DESC, last_message_at DESC
LIMIT {$limit}
";

/* -------------------------------------------------
   EXECUTE
------------------------------------------------- */
$stmt = $pdo->prepare($sql);

$stmt->execute(array_merge(
    [
        $user_id, // IF()
        $user_id, // c.user_one
        $user_id  // c.user_two
    ],
    $params
));

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   FORMAT RESPONSE (HIGHLIGHT)
------------------------------------------------- */
$list = [];

foreach ($rows as $r) {

    $highlight = null;

    if (!empty($r['matched_message'])) {
        $highlight = preg_replace(
            "/(" . preg_quote($search, '/') . ")/i",
            "<mark>$1</mark>",
            $r['matched_message']
        );
    }

    $list[] = [
        "chat_id" => (int)$r['chat_id'],
        "business_profile_id" => (int)$r['business_profile_id'],
        "business_name" => $r['business_name'],
        "logo" => $r['logo'],

        "last_message" => $r['last_message'],
        "last_message_at" => $r['last_message_at'],

        /* jump to this message */
        "matched_message_id" => $r['matched_message_id']
            ? (int)$r['matched_message_id']
            : null,

        /* highlight text */
        "matched_message" => $highlight,
        "matched_message_at" => $r['matched_message_at']
    ];
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Chat search results", [
    "search" => $search,
    "type"   => $type,
    "count"  => count($list),
    "list"   => $list
]);
