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
   INPUT (OPTIONAL FILTERS)
------------------------------------------------- */
$month = (int)($_GET['month'] ?? 0); // 1-12
$year  = (int)($_GET['year'] ?? 0);

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

/* -------------------------------------------------
   BASE WHERE
------------------------------------------------- */
$where = "user_id = ?";
$params = [$user_id];

/* -------------------------------------------------
   MONTH FILTER
------------------------------------------------- */
if ($month > 0 && $year > 0) {
    if ($month < 1 || $month > 12) {
        sendError("Invalid month", 422, "VALIDATION_ERROR");
    }
    if ($year < 2000 || $year > 2100) {
        sendError("Invalid year", 422, "VALIDATION_ERROR");
    }

    $where .= " AND MONTH(meeting_date) = ? AND YEAR(meeting_date) = ?";
    $params[] = $month;
    $params[] = $year;
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
   FETCH DATA
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        id,
        title,
        meeting_date,
        status
    FROM user_meetings
    WHERE $where
    ORDER BY meeting_date ASC
");

$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   GROUP BY DATE
------------------------------------------------- */
$calendar = [];

foreach ($data as $row) {

    $date = date("Y-m-d", strtotime($row['meeting_date']));

    if (!isset($calendar[$date])) {
        $calendar[$date] = [];
    }

    $calendar[$date][] = [
        "meeting_id" => (int)$row['id'],
        "title" => $row['title'],
        "time" => date("H:i", strtotime($row['meeting_date'])),
        "status" => $row['status']
    ];
}

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Calendar data fetched successfully", [
    "filters" => [
        "month" => $month,
        "year" => $year,
        "from_date" => $from_date,
        "to_date" => $to_date
    ],
    "calendar" => $calendar
]);