<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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
   INPUT
------------------------------------------------- */
$search = strtolower(trim($_GET['search'] ?? ''));
$type   = strtolower(trim($_GET['type'] ?? 'all')); // chat | message | all
$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));

if ($search === '') {
    sendError("search keyword is required", 400, "SEARCH_REQUIRED");
}

if (!in_array($type, ['chat', 'message', 'all'])) {
    sendError("Invalid search type", 400, "INVALID_TYPE");
}

$like = '%' . $search . '%';

/* -------------------------------------------------
   BUSINESS SCOPE
------------------------------------------------- */
$whereScope = '';
$scopeParams = [];

if ($role === 'business') {
    $whereScope = 'AND am.business_user_id = ?';
    $scopeParams[] = $user_id;
}

/* -------------------------------------------------
   SEARCH FILTER
------------------------------------------------- */
$filters = [];
$params  = [];

if ($type === 'chat' || $type === 'all') {
    $filters[] = 'LOWER(bp.business_name) LIKE ?';
    $params[]  = $like;
}

if ($type === 'message' || $type === 'all') {
    $filters[] = 'LOWER(am.message) LIKE ?';
    $params[]  = $like;
}

$whereSearch = implode(' OR ', $filters);

/* -------------------------------------------------
   MAIN QUERY (GROUPED BY BUSINESS)
------------------------------------------------- */
$sql = "
SELECT
    bp.id        AS business_profile_id,
    bp.user_id  AS business_user_id,
    bp.business_name,
    bp.logo,

    MAX(am.id) AS matched_message_id,

    (
        SELECT message
        FROM admin_messages x
        WHERE x.business_user_id = bp.user_id
        ORDER BY x.created_at DESC
        LIMIT 1
    ) AS last_message,

    (
        SELECT created_at
        FROM admin_messages x
        WHERE x.business_user_id = bp.user_id
        ORDER BY x.created_at DESC
        LIMIT 1
    ) AS last_message_at,

    (
        SELECT message
        FROM admin_messages x
        WHERE x.business_user_id = bp.user_id
          AND LOWER(x.message) LIKE ?
        ORDER BY x.created_at DESC
        LIMIT 1
    ) AS matched_message,

    (
        SELECT created_at
        FROM admin_messages x
        WHERE x.business_user_id = bp.user_id
          AND LOWER(x.message) LIKE ?
        ORDER BY x.created_at DESC
        LIMIT 1
    ) AS matched_message_at

FROM admin_messages am
JOIN business_profiles bp ON bp.user_id = am.business_user_id
WHERE ($whereSearch)
$whereScope
GROUP BY bp.user_id
ORDER BY matched_message_at DESC, last_message_at DESC
LIMIT {$limit}
";

/* -------------------------------------------------
   EXECUTE
------------------------------------------------- */
$execParams = array_merge(
    $params,
    [$like, $like],     // for sub-queries
    $scopeParams
);

$stmt = $pdo->prepare($sql);
$stmt->execute($execParams);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   FORMAT RESPONSE
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
        "business_profile_id" => (int)$r['business_profile_id'],
        "business_user_id"    => (int)$r['business_user_id'],
        "business_name"       => $r['business_name'],
        "logo"                => $r['logo'],

        "last_message"        => $r['last_message'],
        "last_message_at"     => $r['last_message_at'],

        "matched_message_id"  => (int)$r['matched_message_id'],
        "matched_message"     => $highlight,
        "matched_message_at"  => $r['matched_message_at']
    ];
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Admin chat search results", [
    "search" => $search,
    "type"   => $type,
    "count"  => count($list),
    "list"   => $list
]);
