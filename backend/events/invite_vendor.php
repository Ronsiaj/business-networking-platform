<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* PREFLIGHT */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* DEPENDENCIES */
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

/* METHOD */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

/* AUTH */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

$user_id = (int)$user['id'];

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON input", 400, "INVALID_JSON");
}

$event_id     = (int)($input['event_id'] ?? 0);
$vendor_id    = (int)($input['vendor_id'] ?? 0);
$service_type = trim($input['service_type'] ?? '');

/* VALIDATION */
if (!$event_id || !$vendor_id) {
    sendError("event_id and vendor_id required", 422, "VALIDATION_ERROR");
}

if ($user_id === $vendor_id) {
    sendError("You cannot invite yourself", 422, "VALIDATION_ERROR");
}

/* CHECK EVENT */
$stmt = $pdo->prepare("SELECT id, status FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) sendError("Event not found", 404, "NOT_FOUND");
if ($event['status'] !== 'ONGOING') sendError("Event not active", 409, "INVALID_STATE");

/* GET COMMUNITY */
$stmt = $pdo->prepare("
    SELECT community_id 
    FROM event_communities 
    WHERE event_id = ?
");
$stmt->execute([$event_id]);

$community = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$community) sendError("No community linked", 404, "NOT_FOUND");

$community_id = (int)$community['community_id'];

/* CHECK ADMIN */
$stmt = $pdo->prepare("
    SELECT id FROM community_members 
    WHERE community_id = ? AND user_id = ? AND role = 'ADMIN'
");
$stmt->execute([$community_id, $user_id]);

if (!$stmt->fetch()) {
    sendError("Only community admin can invite vendors", 403, "FORBIDDEN");
}

/* CHECK VENDOR IN COMMUNITY */
$stmt = $pdo->prepare("
    SELECT id FROM community_members 
    WHERE community_id = ? AND user_id = ?
");
$stmt->execute([$community_id, $vendor_id]);

if (!$stmt->fetch()) {
    sendError("Vendor not in community", 403, "FORBIDDEN");
}

/* CHECK USER EXISTS */
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$vendor_id]);

if (!$stmt->fetch()) {
    sendError("Vendor not found", 404, "NOT_FOUND");
}

/* PREVENT DUPLICATE */
$stmt = $pdo->prepare("
    SELECT id FROM event_vendors 
    WHERE event_id = ? AND vendor_id = ?
");
$stmt->execute([$event_id, $vendor_id]);

if ($stmt->fetch()) {
    sendError("Vendor already invited", 409, "ALREADY_INVITED");
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* INSERT EVENT VENDOR */
    $stmt = $pdo->prepare("
        INSERT INTO event_vendors (event_id, vendor_id, service_type, status)
        VALUES (?, ?, ?, 'INVITED')
    ");

    $stmt->execute([
        $event_id,
        $vendor_id,
        $service_type ?: null
    ]);

    $event_vendor_id = (int)$pdo->lastInsertId();

    /* GET ADMIN NAME */
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_name = $admin['name'] ?? 'Admin';

    /* 🔔 NOTIFICATION */
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'EVENT_VENDOR_INVITE', ?, ?)
    ");

    $stmt->execute([
        $vendor_id,              // receiver
        $user_id,                // actor (admin)
        $event_id,               // reference (event)
        "{$admin_name} invited you to participate in an event"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError($e->getMessage(), 500); // debug mode
}

/* RESPONSE */
sendSuccess("Vendor invited successfully", [
    "event_vendor_id" => $event_vendor_id,
    "event_id" => $event_id,
    "vendor_id" => $vendor_id,
    "status" => "INVITED"
]);