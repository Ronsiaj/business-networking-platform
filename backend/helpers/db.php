<?php
require_once __DIR__ . '/../config/config.php';

/*
|--------------------------------------------------------------------------
| PDO Database Connection
|--------------------------------------------------------------------------
| - UTF8MB4 enforced
| - Exception mode enabled
| - Emulated prepares disabled (security)
| - Persistent connection optional
|--------------------------------------------------------------------------
*/

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => "DB_CONNECTION_FAILED",
        "message" => "Database connection failed"
    ]);
    exit;
}
