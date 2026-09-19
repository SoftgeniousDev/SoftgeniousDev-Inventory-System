<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

function authenticateApiRequest(PDO $pdo): array
{
    $headers = function_exists('getallheaders')
        ? getallheaders()
        : [];

    $authorization = '';

    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'authorization') {
            $authorization = trim($value);
            break;
        }
    }

    if ($authorization === '') {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Authorization header is required.'
        ]);

        exit;
    }

    if (!preg_match(
        '/^Bearer\s+(.+)$/i',
        $authorization,
        $matches
    )) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid authorization format. Use Bearer token.'
        ]);

        exit;
    }

    $token = trim($matches[1]);

    if ($token === '') {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'API token is required.'
        ]);

        exit;
    }

    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT
            api_tokens.id AS token_id,
            api_tokens.user_id,
            api_tokens.expires_at,
            users.name,
            users.email,
            users.role
        FROM api_tokens

        INNER JOIN users
            ON api_tokens.user_id = users.id

        WHERE api_tokens.token_hash = ?

        LIMIT 1
    ");

    $stmt->execute([$token_hash]);

    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid API token.'
        ]);

        exit;
    }

    if (
        $user['expires_at'] !== null &&
        strtotime($user['expires_at']) < time()
    ) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'API token has expired.'
        ]);

        exit;
    }

    return $user;
}