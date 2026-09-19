<?php

header('Content-Type: application/json; charset=utf-8');

require_once 'auth.php';

$user = authenticateApiRequest($pdo);

/*
|--------------------------------------------------------------------------
| Only allow GET requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check for a specific product
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

/*
|--------------------------------------------------------------------------
| Get single product
|--------------------------------------------------------------------------
*/

if ($id !== false && $id !== null) {

    $stmt = $pdo->prepare("
        SELECT
            products.id,
            products.name,
            products.sku,
            products.category,
            products.buying_price,
            products.selling_price,
            products.stock_quantity,
            products.reorder_level,
            suppliers.name AS supplier_name,
            products.created_at
        FROM products

        LEFT JOIN suppliers
            ON products.supplier_id = suppliers.id

        WHERE products.id = ?

        LIMIT 1
    ");

    $stmt->execute([$id]);

    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Product not found.'
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'category' => $product['category'],
            'buying_price' => (float) $product['buying_price'],
            'selling_price' => (float) $product['selling_price'],
            'stock_quantity' => (int) $product['stock_quantity'],
            'reorder_level' => (int) $product['reorder_level'],
            'supplier_name' => $product['supplier_name'],
            'created_at' => $product['created_at']
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get all products
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        products.id,
        products.name,
        products.sku,
        products.category,
        products.buying_price,
        products.selling_price,
        products.stock_quantity,
        products.reorder_level,
        suppliers.name AS supplier_name,
        products.created_at
    FROM products

    LEFT JOIN suppliers
        ON products.supplier_id = suppliers.id

    ORDER BY products.id DESC
");

$products = $stmt->fetchAll();

$data = [];

foreach ($products as $product) {

    $data[] = [
        'id' => (int) $product['id'],
        'name' => $product['name'],
        'sku' => $product['sku'],
        'category' => $product['category'],
        'buying_price' => (float) $product['buying_price'],
        'selling_price' => (float) $product['selling_price'],
        'stock_quantity' => (int) $product['stock_quantity'],
        'reorder_level' => (int) $product['reorder_level'],
        'supplier_name' => $product['supplier_name'],
        'created_at' => $product['created_at']
    ];
}

/*
|--------------------------------------------------------------------------
| Return response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'success' => true,
    'count' => count($data),
    'data' => $data
]);

exit;