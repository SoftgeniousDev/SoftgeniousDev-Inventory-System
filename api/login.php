<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Read JSON request body
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON request body.'
    ]);

    exit;
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

/*
|--------------------------------------------------------------------------
| Validate input
|--------------------------------------------------------------------------
*/

if ($email === '' || $password === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);

    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Find user
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        password,
        role
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$email]);

$user = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Verify credentials
|--------------------------------------------------------------------------
*/

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Generate secure token
|--------------------------------------------------------------------------
*/

$token = bin2hex(random_bytes(32));

$token_hash = hash(
    'sha256',
    $token
);

/*
|--------------------------------------------------------------------------
| Token expiration
|--------------------------------------------------------------------------
|
| Token remains valid for 7 days.
|
*/

$expires_at = date(
    'Y-m-d H:i:s',
    time() + (7 * 24 * 60 * 60)
);

/*
|--------------------------------------------------------------------------
| Store token hash
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO api_tokens (
        user_id,
        token_hash,
        expires_at
    )
    VALUES (?, ?, ?)
");

$stmt->execute([
    $user['id'],
    $token_hash,
    $expires_at
]);

/*
|--------------------------------------------------------------------------
| Return API response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'success' => true,
    'message' => 'Authentication successful.',
    'token_type' => 'Bearer',
    'expires_at' => $expires_at,
    'user' => [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role']
    ],
    'access_token' => $token
]);

exit;