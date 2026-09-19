<?php

require_once '../includes/auth.php';
require_once '../config/database.php';

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
        products.sku,
        products.category,
        products.buying_price,
        products.selling_price,
        products.stock_quantity,
        products.reorder_level,
        products.image,
        products.created_at,
        suppliers.name AS supplier_name,
        suppliers.phone AS supplier_phone,
        suppliers.email AS supplier_email
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
    die('Product not found.');
}

$isLowStock =
    $product['stock_quantity']
    <= $product['reorder_level'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= htmlspecialchars($product['name']) ?> |
    SoftgeniousDev
</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #172033;
}

.container {
    max-width: 850px;
    margin: auto;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.topbar h1 {
    font-size: 28px;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}

.btn-secondary {
    background: #e4e7ec;
    color: #344054;
}

.btn-primary {
    background: #1570ef;
    color: white;
}

.card {
    background: white;
    border: 1px solid #e4e7ec;
    border-radius: 14px;
    padding: 28px;
    margin-bottom: 20px;
}

.product-header {
    display: flex;
    gap: 25px;
    align-items: flex-start;
    margin-bottom: 25px;
}

.product-image-container {
    width: 180px;
    height: 180px;
    flex-shrink: 0;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e4e7ec;
    background: #f9fafb;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    color: #667085;
    text-align: center;
    font-size: 14px;
    padding: 15px;
}

.product-title {
    font-size: 25px;
    margin-bottom: 8px;
}

.sku {
    color: #667085;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.info {
    background: #f9fafb;
    border: 1px solid #eaecf0;
    border-radius: 10px;
    padding: 16px;
}

.label {
    font-size: 12px;
    color: #667085;
    margin-bottom: 6px;
}

.value {
    font-size: 17px;
    font-weight: 600;
}

.stock-low {
    color: #b42318;
}

.stock-ok {
    color: #027a48;
}

.supplier {
    margin-top: 25px;
}

.supplier h2 {
    font-size: 18px;
    margin-bottom: 15px;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 600px) {

    .container {
        padding: 18px;
    }

    .topbar {
        display: block;
    }

    .topbar .btn {
        margin-top: 15px;
    }

    .product-header {
        flex-direction: column;
    }

    .product-image-container {
        width: 100%;
        height: 250px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <h1>Product Details</h1>

        <a
            href="index.php"
            class="btn btn-secondary"
        >
            Back to Products
        </a>

    </div>

    <div class="card">

        <div class="product-header">

            <div class="product-image-container">

                <?php if (!empty($product['image'])): ?>

                    <img
                        src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                        alt="<?= htmlspecialchars($product['name']) ?>"
                        class="product-image"
                    >

                <?php else: ?>

                    <div class="no-image">
                        No product image
                    </div>

                <?php endif; ?>

            </div>

            <div>

                <h2 class="product-title">
                    <?= htmlspecialchars($product['name']) ?>
                </h2>

                <p class="sku">
                    SKU:
                    <?= htmlspecialchars($product['sku']) ?>
                </p>

            </div>

        </div>

        <div class="grid">

            <div class="info">

                <div class="label">
                    Product ID
                </div>

                <div class="value">
                    <?= (int) $product['id'] ?>
                </div>

            </div>

            <div class="info">

                <div class="label">
                    Category
                </div>

                <div class="value">
                    <?= htmlspecialchars($product['category'] ?? '—') ?>
                </div>

            </div>

            <div class="info">

                <div class="label">
                    Buying Price
                </div>

                <div class="value">
                    KSh
                    <?= number_format(
                        (float) $product['buying_price'],
                        2
                    ) ?>
                </div>

            </div>

            <div class="info">

                <div class="label">
                    Selling Price
                </div>

                <div class="value">
                    KSh
                    <?= number_format(
                        (float) $product['selling_price'],
                        2
                    ) ?>
                </div>

            </div>

            <div class="info">

                <div class="label">
                    Current Stock
                </div>

                <div
                    class="value <?= $isLowStock
                        ? 'stock-low'
                        : 'stock-ok' ?>"
                >

                    <?= (int) $product['stock_quantity'] ?>

                    <?php if ($isLowStock): ?>

                        — Low Stock

                    <?php endif; ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Reorder Level
                </div>

                <div class="value">
                    <?= (int) $product['reorder_level'] ?>
                </div>

            </div>

        </div>

        <div class="supplier">

            <h2>Supplier</h2>

            <div class="grid">

                <div class="info">

                    <div class="label">
                        Name
                    </div>

                    <div class="value">
                        <?= htmlspecialchars(
                            $product['supplier_name'] ?? '—'
                        ) ?>
                    </div>

                </div>

                <div class="info">

                    <div class="label">
                        Phone
                    </div>

                    <div class="value">
                        <?= htmlspecialchars(
                            $product['supplier_phone'] ?? '—'
                        ) ?>
                    </div>

                </div>

                <div class="info">

                    <div class="label">
                        Email
                    </div>

                    <div class="value">
                        <?= htmlspecialchars(
                            $product['supplier_email'] ?? '—'
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <div class="card">

        <div class="actions">

            <a
                href="qr.php?id=<?= (int) $product['id'] ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-primary"
            >
                Generate QR Code
            </a>

            <a
                href="edit.php?id=<?= (int) $product['id'] ?>"
                class="btn btn-secondary"
            >
                Edit Product
            </a>

        </div>

    </div>

</div>

</body>

</html>