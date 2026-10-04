<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

/*
|--------------------------------------------------------------------------
| REQUIRE AUTH (JWT)
|--------------------------------------------------------------------------
| - Validates Bearer token
| - Decodes JWT
| - Sets $GLOBALS['auth_user'] = ['id','role']
|--------------------------------------------------------------------------
*/
function require_auth(): void
{
    $headers = getallheaders();

    if (empty($headers['Authorization'])) {
        sendError("Authorization header missing", 401, "AUTH_HEADER_MISSING");
    }

    if (!preg_match('/Bearer\s+(\S+)/', $headers['Authorization'], $matches)) {
        sendError("Invalid authorization header format", 401, "INVALID_AUTH_HEADER");
    }

    $token = $matches[1];
    $payload = jwt_decode_token($token);

    if (!$payload) {
        sendError("Invalid or expired token", 401, "INVALID_TOKEN");
    }

    if (empty($payload->id) || empty($payload->role)) {
        sendError("Invalid token payload", 401, "INVALID_TOKEN_PAYLOAD");
    }

    // Set authenticated user globally
    $GLOBALS['auth_user'] = [
        'id'   => (int) $payload->id,
        'role' => $payload->role
    ];
}

/*
|--------------------------------------------------------------------------
| REQUIRE ADMIN
|--------------------------------------------------------------------------
| Usage:
|   require_admin();
|--------------------------------------------------------------------------
*/
function require_admin(): void
{
    require_auth();

    if ($GLOBALS['auth_user']['role'] !== 'admin') {
        sendError("Admin access only", 403, "FORBIDDEN");
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE BUSINESS
|--------------------------------------------------------------------------
| Usage:
|   require_business();
|--------------------------------------------------------------------------
*/
function require_business(): void
{
    require_auth();

    if ($GLOBALS['auth_user']['role'] !== 'business') {
        sendError("Business access only", 403, "FORBIDDEN");
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE STAFF (Optional / Future)
|--------------------------------------------------------------------------
*/
function require_staff(): void
{
    require_auth();

    if ($GLOBALS['auth_user']['role'] !== 'staff') {
        sendError("Staff access only", 403, "FORBIDDEN");
    }
}

