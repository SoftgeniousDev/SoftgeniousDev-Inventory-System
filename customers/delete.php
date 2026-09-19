<?php

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    die('Method Not Allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

    http_response_code(403);

    die('Invalid security token.');
}

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    http_response_code(400);

    die('Invalid customer ID.');
}

/*
|--------------------------------------------------------------------------
| Check customer exists
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$customer = $stmt->fetch();

if (!$customer) {

    http_response_code(404);

    die('Customer not found.');
}

/*
|--------------------------------------------------------------------------
| Check sales history
|--------------------------------------------------------------------------
|
| A customer with existing sales must not be deleted because
| historical sales records depend on the customer relationship.
|
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sales
    WHERE customer_id = ?
");

$stmt->execute([$id]);

$saleCount = (int) $stmt->fetchColumn();

if ($saleCount > 0) {

    http_response_code(409);

    die(
        'This customer cannot be deleted because sales history ' .
        'exists for this customer. Keep the customer record to ' .
        'preserve historical sales data.'
    );
}

/*
|--------------------------------------------------------------------------
| Delete customer
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        DELETE FROM customers
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header('Location: index.php?deleted=1');
    exit;

} catch (PDOException $e) {

    http_response_code(500);

    die(
        'Unable to delete the customer. Please try again later.'
    );
}