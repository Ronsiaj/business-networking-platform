<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
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
if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE','POST'])) {
    sendError("Method not allowed. Use DELETE or POST", 405);
}

/* AUTH */
require_auth();

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || !isset($user['id'])) {
    sendError("Unauthorized", 401);
}

$user_id = (int)$user['id'];
$user_role = $user['role'] ?? 'business';

/* -------------------------------------------------
   INPUT (support both DELETE & POST)
------------------------------------------------- */
$input = json_decode(file_get_contents("php://input"), true);

$review_id = (int)($input['review_id'] ?? ($_GET['review_id'] ?? 0));

/* VALIDATION */
if (!$review_id) {
    sendError("review_id required", 422);
}

/* -------------------------------------------------
   CHECK REVIEW EXISTS
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT reviewer_id 
    FROM reviews 
    WHERE id = ?
");
$stmt->execute([$review_id]);

$review = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$review) {
    sendError("Review not found", 404);
}

/* -------------------------------------------------
   ACCESS CONTROL
------------------------------------------------- */
$isAdmin = ($user_role === 'admin');

if (!$isAdmin && (int)$review['reviewer_id'] !== $user_id) {
    sendError("You can delete only your reviews", 403);
}

/* -------------------------------------------------
   DELETE
------------------------------------------------- */
try {

    $stmt = $pdo->prepare("
        DELETE FROM reviews 
        WHERE id = ?
    ");

    $stmt->execute([$review_id]);

} catch (Exception $e) {
    sendError("Failed to delete review", 500);
}

/* RESPONSE */
sendSuccess("Review deleted successfully", [
    "review_id" => $review_id
]);