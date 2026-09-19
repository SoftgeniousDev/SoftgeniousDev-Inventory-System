<?php

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    die('Method Not Allowed.');
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

    http_response_code(403);

    die('Invalid security token.');
}

// Get product ID
$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    http_response_code(400);

    die('Invalid product ID.');
}

// Check that product exists
$stmt = $pdo->prepare("
    SELECT id, name, image
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {

    http_response_code(404);

    die('Product not found.');
}

// Check purchase history
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM purchase_items
    WHERE product_id = ?
");

$stmt->execute([$id]);

$purchaseCount = (int) $stmt->fetchColumn();

// Check sales history
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sale_items
    WHERE product_id = ?
");

$stmt->execute([$id]);

$saleCount = (int) $stmt->fetchColumn();

// Do not delete products with transaction history
if ($purchaseCount > 0 || $saleCount > 0) {

    http_response_code(409);

    die(
        'This product cannot be deleted because it has transaction history. ' .
        'Remove or archive the related transactions instead.'
    );
}

try {

    // Delete product
    $stmt = $pdo->prepare("
        DELETE FROM products
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    // Delete stored product image if one exists
    if (!empty($product['image'])) {

        $imagePath =
            __DIR__ .
            '/../uploads/products/' .
            basename($product['image']);

        if (is_file($imagePath)) {
            unlink($imagePath);
        }
    }

    header('Location: index.php?deleted=1');
    exit;

} catch (PDOException $e) {

    http_response_code(500);

    die(
        'Unable to delete the product. Please try again later.'
    );
}