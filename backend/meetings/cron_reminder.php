<?php
require_once __DIR__ . '/../helpers/db.php';

/* GET MEETINGS TO REMIND */
$stmt = $pdo->prepare("
SELECT id, user_id, title, meeting_date
FROM user_meetings
WHERE 
    status = 'SCHEDULED'
    AND reminder_sent = 0
    AND DATE(
        DATE_SUB(meeting_date, INTERVAL reminder_days_before DAY)
    ) = CURDATE()
");

$stmt->execute();
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($list as $m) {

    /* 🔔 NOTIFICATION */
    $stmt2 = $pdo->prepare("
    INSERT INTO notifications
    (user_id, actor_user_id, type, reference_id, message)
    VALUES (?, ?, 'MEETING_REMINDER', ?, ?)
    ");

    $stmt2->execute([
        $m['user_id'],
        $m['user_id'],
        $m['id'],
        "Reminder: '{$m['title']}' on " . date('d M Y', strtotime($m['meeting_date']))
    ]);

    /* MARK SENT */
    $stmt3 = $pdo->prepare("
    UPDATE user_meetings SET reminder_sent = 1 WHERE id = ?
    ");
    $stmt3->execute([$m['id']]);
}