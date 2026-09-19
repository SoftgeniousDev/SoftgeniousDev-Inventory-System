<?php

require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');

if ($query === '') {
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
    exit;
}

$search = '%' . $query . '%';

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
        suppliers.name AS supplier_name
    FROM products

    LEFT JOIN suppliers
        ON products.supplier_id = suppliers.id

    WHERE
        products.name LIKE ?
        OR products.sku LIKE ?
        OR products.category LIKE ?
        OR suppliers.name LIKE ?

    ORDER BY products.id DESC

    LIMIT 50
");

$stmt->execute([
    $search,
    $search,
    $search,
    $search
]);

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
        'supplier_name' => $product['supplier_name']
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($data),
    'data' => $data
]);

exit;