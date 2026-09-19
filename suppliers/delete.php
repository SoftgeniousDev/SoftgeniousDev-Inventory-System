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

    die('Invalid supplier ID.');
}

/*
|--------------------------------------------------------------------------
| Check supplier exists
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM suppliers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$supplier = $stmt->fetch();

if (!$supplier) {

    http_response_code(404);

    die('Supplier not found.');
}

/*
|--------------------------------------------------------------------------
| Check transaction history
|--------------------------------------------------------------------------
|
| A supplier should not be deleted if purchases already reference it.
| This protects historical transaction records.
|
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM purchases
    WHERE supplier_id = ?
");

$stmt->execute([$id]);

$purchaseCount = (int) $stmt->fetchColumn();

if ($purchaseCount > 0) {

    http_response_code(409);

    die(
        'This supplier cannot be deleted because purchase history ' .
        'exists for this supplier. Remove or archive the related ' .
        'transactions instead.'
    );
}

/*
|--------------------------------------------------------------------------
| Delete supplier
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        DELETE FROM suppliers
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header('Location: index.php?deleted=1');
    exit;

} catch (PDOException $e) {

    http_response_code(500);

    die(
        'Unable to delete the supplier. Please try again later.'
    );
}