<?php

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    http_response_code(400);
    die('Invalid product ID.');
}

$stmt = $pdo->prepare("
    SELECT
        products.id,
        products.name,
        products.sku
    FROM products
    WHERE products.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    die('Product not found.');
}

/*
|--------------------------------------------------------------------------
| QR Code Destination
|--------------------------------------------------------------------------
*/

$url = 'http://localhost/softgeniousdev-inventory/products/view.php?id='
     . (int) $product['id'];

/*
|--------------------------------------------------------------------------
| Create QR Code Builder
|--------------------------------------------------------------------------
*/

$builder = new Builder(
    writer: new PngWriter(),
    data: $url,
    size: 400,
    margin: 20
);

/*
|--------------------------------------------------------------------------
| Generate QR Code
|--------------------------------------------------------------------------
*/

$result = $builder->build();

/*
|--------------------------------------------------------------------------
| Output PNG
|--------------------------------------------------------------------------
*/

header('Content-Type: ' . $result->getMimeType());

echo $result->getString();

exit;