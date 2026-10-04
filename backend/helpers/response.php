<?php

/*
|--------------------------------------------------------------------------
| JSON RESPONSE HELPERS
|--------------------------------------------------------------------------
| Standard response format for entire project
|--------------------------------------------------------------------------
*/

/**
 * Success response
 *
 * @param string $message
 * @param array|null $data
 * @param int $statusCode
 */
function sendSuccess(string $message, array $data = null, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $response = [
        "success" => true,
        "message" => $message
    ];

    if (!is_null($data)) {
        $response["data"] = $data;
    }

    echo json_encode($response);
    exit;
}

/**
 * Error response
 *
 * @param string $message
 * @param int $statusCode
 * @param string|null $errorCode
 */
function sendError(string $message, int $statusCode = 400, string $errorCode = null): void
{
    http_response_code($statusCode);

    $response = [
        "success" => false,
        "message" => $message
    ];

    if ($errorCode) {
        $response["error"] = $errorCode;
    }

    echo json_encode($response);
    exit;
}

/**
 * Validation error (shortcut)
 *
 * @param string $message
 */
function sendValidationError(string $message): void
{
    sendError($message, 400, "VALIDATION_ERROR");
}

/**
 * Unauthorized response
 */
function sendUnauthorized(): void
{
    sendError("Unauthorized", 401, "UNAUTHORIZED");
}

/**
 * Forbidden response
 */
function sendForbidden(string $message = "Access denied"): void
{
    sendError($message, 403, "FORBIDDEN");
}

/**
 * Server error
 */
function sendServerError(): void
{
    sendError("Internal server error", 500, "SERVER_ERROR");
}
