<?php

require_once '../includes/auth.php';
require_once '../config/database.php';

$purchase_id = (int) ($_GET['id'] ?? 0);

if ($purchase_id <= 0) {
    die('Invalid purchase ID.');
}

/*
|--------------------------------------------------------------------------
| Purchase information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        purchases.id,
        purchases.total_amount,
        purchases.purchase_date,
        suppliers.name AS supplier_name,
        suppliers.phone AS supplier_phone,
        suppliers.email AS supplier_email,
        suppliers.address AS supplier_address,
        users.name AS recorded_by
    FROM purchases

    INNER JOIN suppliers
        ON purchases.supplier_id = suppliers.id

    INNER JOIN users
        ON purchases.user_id = users.id

    WHERE purchases.id = ?
");

$stmt->execute([$purchase_id]);

$purchase = $stmt->fetch();

if (!$purchase) {
    die('Purchase not found.');
}

/*
|--------------------------------------------------------------------------
| Purchase items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        purchase_items.quantity,
        purchase_items.unit_cost,
        purchase_items.subtotal,
        products.name AS product_name,
        products.sku
    FROM purchase_items

    INNER JOIN products
        ON purchase_items.product_id = products.id

    WHERE purchase_items.purchase_id = ?

    ORDER BY purchase_items.id ASC
");

$stmt->execute([$purchase_id]);

$items = $stmt->fetchAll();

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
        Purchase #<?= $purchase['id'] ?> - SoftgeniousDev
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        h1 {
            margin-top: 0;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-box {
            background: #f7f7f7;
            padding: 15px;
            border-radius: 6px;
        }

        .label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }

        .value {
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f1f1;
        }

        .number {
            text-align: right;
        }

        .total {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            margin-top: 20px;
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 14px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <a
        class="back"
        href="index.php"
    >
        ← Back to Purchases
    </a>

    <div class="card">

        <h1>
            Purchase #<?= $purchase['id'] ?>
        </h1>

        <div class="details">

            <div class="detail-box">

                <div class="label">
                    Supplier
                </div>

                <div class="value">
                    <?= htmlspecialchars($purchase['supplier_name']) ?>
                </div>

            </div>

            <div class="detail-box">

                <div class="label">
                    Recorded By
                </div>

                <div class="value">
                    <?= htmlspecialchars($purchase['recorded_by']) ?>
                </div>

            </div>

            <div class="detail-box">

                <div class="label">
                    Supplier Phone
                </div>

                <div class="value">
                    <?= htmlspecialchars($purchase['supplier_phone'] ?? '-') ?>
                </div>

            </div>

            <div class="detail-box">

                <div class="label">
                    Purchase Date
                </div>

                <div class="value">
                    <?= htmlspecialchars($purchase['purchase_date']) ?>
                </div>

            </div>

        </div>

    </div>

    <div class="card">

        <h2>Purchased Products</h2>

        <table>

            <thead>

                <tr>

                    <th>Product</th>

                    <th>SKU</th>

                    <th class="number">Quantity</th>

                    <th class="number">Unit Cost</th>

                    <th class="number">Subtotal</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($items as $item): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($item['sku']) ?>
                        </td>

                        <td class="number">
                            <?= (int) $item['quantity'] ?>
                        </td>

                        <td class="number">
                            KSh <?= number_format($item['unit_cost'], 2) ?>
                        </td>

                        <td class="number">
                            KSh <?= number_format($item['subtotal'], 2) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <div class="total">

            Total:
            KSh <?= number_format($purchase['total_amount'], 2) ?>

        </div>

    </div>

</div>

</body>

</html>