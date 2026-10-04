<?php
header("Content-Type: application/json; charset=utf-8");
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
   DEPENDENCIES
------------------------------------------------- */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH
------------------------------------------------- */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;
if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$user_id = (int)$user['id'];

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$type = strtolower(trim($_GET['type'] ?? 'all')); // today | upcoming | past
$q = strtolower(trim($_GET['q'] ?? ''));

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

/* -------------------------------------------------
   BASE WHERE
------------------------------------------------- */
$where = "user_id = ?";
$params = [$user_id];

/* -------------------------------------------------
   TYPE FILTER
------------------------------------------------- */
if ($type === 'today') {
    $where .= " AND DATE(meeting_date) = CURDATE()";
} elseif ($type === 'upcoming') {
    $where .= " AND meeting_date > NOW()";
} elseif ($type === 'past') {
    $where .= " AND meeting_date < NOW()";
}

/* -------------------------------------------------
   DATE RANGE FILTER
------------------------------------------------- */
if ($from_date !== '') {
    if (!strtotime($from_date)) {
        sendError("Invalid from_date", 422, "INVALID_DATE");
    }
    $where .= " AND DATE(meeting_date) >= ?";
    $params[] = $from_date;
}

if ($to_date !== '') {
    if (!strtotime($to_date)) {
        sendError("Invalid to_date", 422, "INVALID_DATE");
    }
    $where .= " AND DATE(meeting_date) <= ?";
    $params[] = $to_date;
}

/* -------------------------------------------------
   SEARCH FILTER
------------------------------------------------- */
if ($q !== '') {
    $where .= " AND (
        LOWER(title) LIKE ?
        OR LOWER(description) LIKE ?
        OR LOWER(status) LIKE ?
    )";

    $search = "%{$q}%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM user_meetings WHERE $where
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH DATA
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        id,
        title,
        description,
        meeting_date,
        reminder_days_before,
        status,
        created_at
    FROM user_meetings
    WHERE $where
    ORDER BY meeting_date ASC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Meetings fetched successfully", [
    "filters" => [
        "type" => $type,
        "q" => $q,
        "from_date" => $from_date,
        "to_date" => $to_date
    ],
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);