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
   AUTH (ADMIN / BUSINESS)
------------------------------------------------- */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$user_id   = (int)$user['id'];
$user_role = $user['role'];

/* -------------------------------------------------
   INPUT (QUERY PARAMS)
------------------------------------------------- */
$page    = max(1, (int)($_GET['page']  ?? 1));
$limit   = min(100, max(1, (int)($_GET['limit'] ?? 10)));
$offset  = ($page - 1) * $limit;

$q           = trim($_GET['q']           ?? '');
$status      = strtoupper(trim($_GET['status']      ?? ''));
$is_verified = $_GET['is_verified'] ?? '';
$from_date   = $_GET['from_date']   ?? '';
$to_date     = $_GET['to_date']     ?? '';

/* -------------------------------------------------
   BASE QUERY
------------------------------------------------- */
$where  = [];
$params = [];

/* Role-based filter */
if ($user_role !== 'admin') {
    $where[]  = "bp.user_id = ?";
    $params[] = $user_id;
}

/* Status filter */
if ($status && in_array($status, ['APPROVED', 'REJECTED', 'PENDING'])) {
    $where[]  = "bp.verification_status = ?";
    $params[] = $status;
}

/* is_verified filter */
if ($is_verified !== '') {
    if (!in_array($is_verified, ['0', '1'])) {
        sendError("Invalid is_verified value", 422, "VALIDATION_ERROR");
    }
    $where[]  = "bp.is_verified = ?";
    $params[] = (int)$is_verified;
}

/* Date filter */
if ($from_date) {
    $where[]  = "DATE(bp.created_at) >= ?";
    $params[] = $from_date;
}

if ($to_date) {
    $where[]  = "DATE(bp.created_at) <= ?";
    $params[] = $to_date;
}

/* Search */
if ($q !== '') {
    $where[]  = "(bp.business_name LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $search   = "%$q%";
    array_push($params, $search, $search, $search, $search);
}

/* Combine WHERE */
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/* -------------------------------------------------
   MAIN QUERY  — ✅ city, gst_number, license_number added
------------------------------------------------- */
$sql = "
    SELECT
        bp.id,
        bp.user_id,
        bp.business_name,
        bp.city,
        bp.gst_number,
        bp.license_number,
        bp.verification_status,
        bp.is_verified,
        bp.verification_reason,
        bp.created_at,
        bp.verified_at,

        u.name,
        u.email,
        u.mobile,
        u.status AS user_status

    FROM business_profiles bp
    JOIN users u ON u.id = bp.user_id

    $whereSQL

    ORDER BY bp.id DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   TOTAL COUNT
------------------------------------------------- */
$countSql = "
    SELECT COUNT(*)
    FROM business_profiles bp
    JOIN users u ON u.id = bp.user_id
    $whereSQL
";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
sendSuccess("Business profiles fetched", [
    "page"  => $page,
    "limit" => $limit,
    "total" => $total,
    "data"  => $data
]);