<?php
function createNotification(
    PDO $pdo,
    int $user_id,
    int $actor_user_id,
    string $type,
    ?int $reference_id,
    string $message
) {
    $stmt = $pdo->prepare("
        INSERT INTO notifications
            (user_id, actor_user_id, type, reference_id, message, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $actor_user_id,
        $type,
        $reference_id,
        $message
    ]);
}
