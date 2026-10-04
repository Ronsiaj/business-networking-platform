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

/* -------------------------------------------------
   SEARCH FILTER
------------------------------------------------- */
$where = "";
$params = [];

if ($q !== '') {
    $where = "WHERE u.name LIKE ?";
    $params[] = "%$q%";
}

/* -------------------------------------------------
   TOTAL COUNT (vendors with reviews)
------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT r.vendor_id)
    FROM reviews r
    JOIN users u ON u.id = r.vendor_id
    $where
");

$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* -------------------------------------------------
   FETCH TOP RATED VENDORS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        r.vendor_id,

        u.name,
        u.mobile,
        u.email,

        COUNT(r.id) AS total_reviews,
        ROUND(AVG(r.rating), 2) AS avg_rating,

        SUM(CASE WHEN r.deal_id IS NOT NULL THEN 1 ELSE 0 END) AS total_deals,
        SUM(CASE WHEN r.event_id IS NOT NULL THEN 1 ELSE 0 END) AS total_events

    FROM reviews r
    JOIN users u ON u.id = r.vendor_id
    $where
    GROUP BY r.vendor_id
    ORDER BY avg_rating DESC, total_reviews DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Top rated vendors fetched", [
    "pagination" => [
        "page"  => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int) ceil($total / $limit)
    ],
    "list" => $data
]);