<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* PREFLIGHT */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* DEPENDENCIES */
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* METHOD */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET", 405);
}

/* -------------------------------------------------
   INPUT
------------------------------------------------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$q = trim($_GET['q'] ?? '');
$min_rating = (float)($_GET['min_rating'] ?? 0);
$min_completed = (int)($_GET['min_completed'] ?? 0);

/* -------------------------------------------------
   BASE QUERY (OPTIMIZED 🔥)
------------------------------------------------- */
$sql = "
SELECT 
    u.id AS vendor_id,
    u.name,
    u.mobile,
    u.email,

    /* REVIEW */
    COUNT(r.id) AS total_reviews,
    IFNULL(ROUND(AVG(r.rating),2),0) AS avg_rating,

    /* DEAL COUNT */
    COUNT(DISTINCT vd.id) AS completed_deals,

    /* EVENT COUNT */
    COUNT(DISTINCT ev.id) AS completed_events

FROM users u

LEFT JOIN reviews r 
    ON r.vendor_id = u.id

LEFT JOIN vendor_deals vd 
    ON vd.receiver_id = u.id 
    AND vd.status = 'COMPLETED'

LEFT JOIN event_vendors ev 
    ON ev.vendor_id = u.id

LEFT JOIN events e 
    ON e.id = ev.event_id 
    AND e.status = 'COMPLETED'

WHERE u.status = 'ACTIVE'
";

/* SEARCH */
$params = [];

if ($q !== '') {
    $sql .= " AND u.name LIKE ? ";
    $params[] = "%$q%";
}

/* GROUP */
$sql .= " GROUP BY u.id ";

/* -------------------------------------------------
   HAVING FILTERS (NO ALIAS ❌)
------------------------------------------------- */
$having = [];

if ($min_rating > 0) {
    $having[] = "AVG(r.rating) >= " . $min_rating;
}

if ($min_completed > 0) {
    $having[] = "(COUNT(DISTINCT vd.id) + COUNT(DISTINCT ev.id)) >= " . $min_completed;
}

if (!empty($having)) {
    $sql .= " HAVING " . implode(" AND ", $having);
}

/* -------------------------------------------------
   ORDER (RANKING 🔥)
------------------------------------------------- */
$sql .= "
ORDER BY 
    (
        (IFNULL(AVG(r.rating),0) * 0.6) + 
        ((COUNT(DISTINCT vd.id) + COUNT(DISTINCT ev.id)) * 0.4)
    ) DESC,
    AVG(r.rating) DESC
LIMIT $limit OFFSET $offset
";

/* -------------------------------------------------
   EXECUTE
------------------------------------------------- */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   TOTAL COUNT (SIMPLE)
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users 
    WHERE status = 'ACTIVE'
");
$countStmt->execute();

$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Top vendors fetched", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);