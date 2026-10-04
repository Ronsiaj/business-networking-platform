<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

/* -------------------------------------------------
   PREFLIGHT
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* -------------------------------------------------
   METHOD VALIDATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST", 405, "METHOD_NOT_ALLOWED");
}

/* -------------------------------------------------
   AUTH : BUSINESS ONLY
------------------------------------------------- */
require_business();
$user_id = $GLOBALS['auth_user']['id'];

/* -------------------------------------------------
   INPUT (multipart/form-data)
------------------------------------------------- */
$data = $_POST;

$content   = isset($data['content']) ? strtolower(trim($data['content'])) : '';
$post_type = strtoupper(trim($data['post_type'] ?? 'TEXT'));

/* -------------------------------------------------
   VALIDATE POST TYPE
------------------------------------------------- */
$allowedPostTypes = ['TEXT', 'IMAGE', 'VIDEO'];

if (!in_array($post_type, $allowedPostTypes)) {
    sendError(
        "Invalid post_type",
        400,
        "INVALID_POST_TYPE"
    );
}

/* -------------------------------------------------
   VALIDATE CONTENT / MEDIA
------------------------------------------------- */
if ($content === '' && empty($_FILES['media']['name'])) {
    sendError(
        "Post content or media is required",
        400,
        "CONTENT_REQUIRED"
    );
}

/* -------------------------------------------------
   MEDIA VALIDATION (IF PRESENT)
------------------------------------------------- */
$mediaPath = null;
$mediaType = null;

if (!empty($_FILES['media']['name'])) {

    $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));

    $imageExt = ['jpg','jpeg','png','webp'];
    $videoExt = ['mp4','mov','avi','mkv'];

    if (in_array($ext, $imageExt)) {
        $mediaType = 'IMAGE';
    } elseif (in_array($ext, $videoExt)) {
        $mediaType = 'VIDEO';
    } else {
        sendError(
            "Invalid media format",
            400,
            "INVALID_MEDIA_FORMAT"
        );
    }

    if ($_FILES['media']['size'] > 10 * 1024 * 1024) {
        sendError(
            "Media size must be below 10MB",
            400,
            "MEDIA_TOO_LARGE"
        );
    }
}

/* -------------------------------------------------
   TRANSACTION
------------------------------------------------- */
try {
    $pdo->beginTransaction();

    /* INSERT POST */
    $stmt = $pdo->prepare("
        INSERT INTO posts
        (
            user_id,
            content,
            post_type,
            status,
            created_at
        )
        VALUES (?, ?, ?, 'ACTIVE', NOW())
    ");
    $stmt->execute([
        $user_id,
        $content ?: null,
        $post_type
    ]);

    $post_id = (int) $pdo->lastInsertId();

    /* -------------------------------------------------
       MEDIA UPLOAD (OPTIONAL)
    ------------------------------------------------- */
    if (!empty($_FILES['media']['name'])) {

        $uploadDir = __DIR__ . '/../uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid('post_') . '.' . $ext;
        $mediaPath = 'uploads/posts/' . $fileName;

        if (!move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $fileName)) {
            throw new Exception("Media upload failed");
        }

        $stmt = $pdo->prepare("
            INSERT INTO post_media
            (
                post_id,
                media_url,
                media_type,
                created_at
            )
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $post_id,
            $mediaPath,
            $mediaType
        ]);
    }

    $pdo->commit();

    sendSuccess(
        "Post created successfully",
        [
            "post_id"   => $post_id,
            "post_type" => $post_type
        ]
    );

} catch (Throwable $e) {
    $pdo->rollBack();

    sendError(
        "Failed to create post",
        500,
        "SERVER_ERROR"
    );
}
