<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function jwt_generate_token(array $payload): string
{
    $issuedAt = time();

    $payload = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $issuedAt + JWT_TTL,
        'iss' => JWT_ISSUER
    ]);

    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

function jwt_decode_token(string $token)
{
    try {
        return JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    } catch (Exception $e) {
        return false;
    }
}
