<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* PREFLIGHT */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* METHOD */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed", 405);
}

/* AUTH (ANY LOGGED USER) */
require_auth();
$user_id = (int)$GLOBALS['auth_user']['id'];

/* UPSERT LAST SEEN */
$stmt = $pdo->prepare("
    INSERT INTO user_last_seen (user_id, last_seen)
    VALUES (?, NOW())
    ON DUPLICATE KEY UPDATE last_seen = NOW()
");
$stmt->execute([$user_id]);

sendSuccess("Heartbeat updated");
