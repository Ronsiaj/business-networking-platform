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
    sendError("Method not allowed", 405);
}

/* AUTH */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$user_id = (int)$user['id'];

/* INPUT */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    sendError("Invalid JSON input", 400);
}

$event_id     = (int)($input['event_id'] ?? 0);
$community_id = (int)($input['community_id'] ?? 0);

if (!$event_id || !$community_id) {
    sendError("event_id and community_id required", 422);
}

/* CHECK EVENT */
$stmt = $pdo->prepare("SELECT id, client_id, status FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) sendError("Event not found", 404);
if ((int)$event['client_id'] !== $user_id) sendError("Unauthorized", 403);
if ($event['status'] !== 'PLANNING') sendError("Invalid event state", 409);

/* CHECK COMMUNITY */
$stmt = $pdo->prepare("SELECT id, name FROM communities WHERE id = ? AND status='ACTIVE'");
$stmt->execute([$community_id]);
$community = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$community) sendError("Community not found", 404);

/* GET ADMIN */
$stmt = $pdo->prepare("
    SELECT user_id 
    FROM community_members 
    WHERE community_id = ? AND role='ADMIN'
    LIMIT 1
");
$stmt->execute([$community_id]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) sendError("Admin not found", 404);

$admin_id = (int)$admin['user_id'];

/* GET SERVICE TYPE */
$stmt = $pdo->prepare("
    SELECT c.name 
    FROM user_interest_map uim
    JOIN categories c ON c.id = uim.category_id
    WHERE uim.user_id = ?
    ORDER BY uim.score DESC
    LIMIT 1
");
$stmt->execute([$admin_id]);

$category = $stmt->fetch(PDO::FETCH_ASSOC);
$service_type = $category ? $category['name'] : $community['name'];

/* PREVENT DUPLICATE */
$stmt = $pdo->prepare("
    SELECT id FROM event_vendors 
    WHERE event_id = ? AND vendor_id = ?
");
$stmt->execute([$event_id, $admin_id]);

if ($stmt->fetch()) {
    sendError("Admin already added", 409);
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {

    $pdo->beginTransaction();

    /* LINK COMMUNITY */
    $stmt = $pdo->prepare("
        INSERT INTO event_communities (event_id, community_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$event_id, $community_id]);

    /* ADD ADMIN AS VENDOR */
    $stmt = $pdo->prepare("
        INSERT INTO event_vendors (event_id, vendor_id, service_type, status)
        VALUES (?, ?, ?, 'ACCEPTED')
    ");
    $stmt->execute([
        $event_id,
        $admin_id,
        $service_type
    ]);

    /* UPDATE EVENT STATUS */
    $stmt = $pdo->prepare("
        UPDATE events 
        SET status='ONGOING'
        WHERE id = ?
    ");
    $stmt->execute([$event_id]);

    /* -----------------------------------------
       🔔 NOTIFICATION TO ADMIN
    ----------------------------------------- */

    // get client name
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    $client_name = $client['name'] ?? 'Client';

    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_user_id, type, reference_id, message)
        VALUES (?, ?, 'EVENT_COMMUNITY_LINKED', ?, ?)
    ");

    $stmt->execute([
        $admin_id,              // receiver
        $user_id,               // actor (client)
        $event_id,
        "{$client_name} added your community '{$community['name']}' to an event"
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    sendError($e->getMessage(), 500); // debug mode
}

/* RESPONSE */
sendSuccess("Community linked & admin added", [
    "event_id" => $event_id,
    "community_id" => $community_id,
    "admin_id" => $admin_id,
    "service_type" => $service_type,
    "status" => "ONGOING"
]);