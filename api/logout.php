<?php

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| Get Authorization Header
|--------------------------------------------------------------------------
*/

$headers = getallheaders();

$authorization = '';

foreach ($headers as $key => $value) {

    if (strtolower($key) === 'authorization') {

        $authorization = trim($value);

        break;
    }
}


/*
|--------------------------------------------------------------------------
| Validate Bearer Token
|--------------------------------------------------------------------------
*/

if (
    empty($authorization) ||
    !preg_match(
        '/^Bearer\s+(.+)$/i',
        $authorization,
        $matches
    )
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Bearer token required.'
    ]);

    exit;
}


$token = trim($matches[1]);


/*
|--------------------------------------------------------------------------
| Hash Token
|--------------------------------------------------------------------------
*/

$tokenHash =
    hash(
        'sha256',
        $token
    );


/*
|--------------------------------------------------------------------------
| Revoke Token
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    DELETE FROM api_tokens
    WHERE token_hash = ?
");


$stmt->execute([
    $tokenHash
]);


if ($stmt->rowCount() === 0) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid or already revoked token.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'message' => 'API token revoked successfully.'
]);

exit;