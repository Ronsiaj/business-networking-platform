<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

require_admin();

/* -------------------------------------------------
    1️⃣ CAPTURE INCOMING FILTERS
------------------------------------------------- */
$days       = isset($_GET['days'])   ? (int)$_GET['days']   : 7;
$topLimit   = isset($_GET['top'])    ? (int)$_GET['top']    : 5;
$recentLimit = isset($_GET['recent']) ? (int)$_GET['recent'] : 5;

// Sanitize: prevent negative or zero values
$days        = max(1, $days);
$topLimit    = max(1, $topLimit);
$recentLimit = max(1, $recentLimit);

// DATE_SUB(CURDATE(), INTERVAL ($days-1) DAY) gives exactly $days rows
// e.g. days=7 → INTERVAL 6 DAY → Day-6..Day-0(today) = 7 days ✅
$intervalDays = $days - 1;

/* -------------------------------------------------
    HELPER: Fill missing dates so charts are always
    continuous even when no data exists for a day
------------------------------------------------- */
function fillMissingDates(array $data, int $days): array {
    // Build a full date map for the range
    $map = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $map[$date] = 0;
    }

    // Overwrite with real data
    foreach ($data as $row) {
        if (isset($map[$row['day']])) {
            $map[$row['day']] = (int)$row['total'];
        }
    }

    // Convert back to indexed array
    $result = [];
    foreach ($map as $date => $total) {
        $result[] = ['day' => $date, 'total' => $total];
    }
    return $result;
}

/* -------------------------------------------------
    2️⃣ SUMMARY COUNTS (Static totals)
------------------------------------------------- */
$stats = [];
$stats['total_businesses']          = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='business'")->fetchColumn();
$stats['active_businesses']         = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='business' AND status='ACTIVE'")->fetchColumn();
$stats['inactive_businesses']       = $stats['total_businesses'] - $stats['active_businesses'];
$stats['total_posts']               = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$stats['total_chats']               = (int)$pdo->query("SELECT COUNT(*) FROM chats")->fetchColumn();
$stats['total_messages']            = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();
$stats['today_messages']            = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['admin_unread_notifications'] = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND is_read = 0")->fetchColumn();

/* -------------------------------------------------
    3️⃣ MOST ACTIVE BUSINESSES (Filtered by $topLimit)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        bp.id AS business_profile_id,
        bp.business_name,
        bp.logo,
        (SELECT COUNT(*) FROM posts p WHERE p.user_id = bp.user_id) AS post_count,
        (SELECT COUNT(*) FROM chat_messages cm WHERE cm.sender_id = bp.user_id) AS message_count
    FROM business_profiles bp
    ORDER BY message_count DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $topLimit, PDO::PARAM_INT);
$stmt->execute();
$topBusinesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
    4️⃣ RECENT ADMIN CHATS (Filtered by $recentLimit)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        cal.chat_id,
        cal.message_count,
        cal.last_message_at,
        bp.id AS business_profile_id,
        bp.business_name
    FROM chat_activity_log cal
    LEFT JOIN business_profiles bp ON bp.user_id = cal.business_one OR bp.user_id = cal.business_two
    ORDER BY cal.last_message_at DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $recentLimit, PDO::PARAM_INT);
$stmt->execute();
$recentChats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
    5️⃣ RECENT POSTS (Filtered by $recentLimit)
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        p.id AS post_id,
        p.created_at,
        bp.business_name
    FROM posts p
    JOIN business_profiles bp ON bp.user_id = p.user_id
    ORDER BY p.created_at DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $recentLimit, PDO::PARAM_INT);
$stmt->execute();
$recentPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
    6️⃣ GRAPH DATA (Filtered by $days, with gap-fill)
------------------------------------------------- */

// Messages per day
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM chat_messages
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$stmt->bindValue(':days', $intervalDays, PDO::PARAM_INT);
$stmt->execute();
$messageGraph = fillMissingDates($stmt->fetchAll(PDO::FETCH_ASSOC), $days);

// Business registrations per day
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM users
    WHERE role = 'business'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$stmt->bindValue(':days', $intervalDays, PDO::PARAM_INT);
$stmt->execute();
$businessGraph = fillMissingDates($stmt->fetchAll(PDO::FETCH_ASSOC), $days);

// Posts per day
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM posts
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$stmt->bindValue(':days', $intervalDays, PDO::PARAM_INT);
$stmt->execute();
$postGraph = fillMissingDates($stmt->fetchAll(PDO::FETCH_ASSOC), $days);

/* -------------------------------------------------
    7️⃣ SEND RESPONSE
------------------------------------------------- */
sendSuccess("Admin dashboard data fetched successfully", [
    "summary"        => $stats,
    "top_businesses" => $topBusinesses,
    "recent_chats"   => $recentChats,
    "recent_posts"   => $recentPosts,
    "graphs"         => [
        "messages_per_day"       => $messageGraph,
        "business_registrations" => $businessGraph,
        "posts_per_day"          => $postGraph
    ]
]);