<?php

require_once '../includes/auth.php';
require_once '../config/database.php';

$sale_id = (int) ($_GET['id'] ?? 0);

if ($sale_id <= 0) {
    die('Invalid sale ID.');
}


/*
|--------------------------------------------------------------------------
| Sale information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sales.id,
        sales.total_amount,
        sales.sale_date,
        customers.name AS customer_name,
        customers.phone AS customer_phone,
        customers.email AS customer_email,
        customers.address AS customer_address,
        users.name AS recorded_by
    FROM sales

    LEFT JOIN customers
        ON sales.customer_id = customers.id

    INNER JOIN users
        ON sales.user_id = users.id

    WHERE sales.id = ?
");

$stmt->execute([$sale_id]);

$sale = $stmt->fetch();

if (!$sale) {
    die('Sale not found.');
}


/*
|--------------------------------------------------------------------------
| Sale items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sale_items.quantity,
        sale_items.unit_price,
        sale_items.subtotal,
        products.name AS product_name,
        products.sku
    FROM sale_items

    INNER JOIN products
        ON sale_items.product_id = products.id

    WHERE sale_items.sale_id = ?

    ORDER BY sale_items.id ASC
");

$stmt->execute([$sale_id]);

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
        Sale #<?= (int) $sale['id'] ?> - SoftgeniousDev
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 30px;

            font-family:
                Arial,
                sans-serif;

            background: #f4f6f8;

            color: #172033;

        }


        .container {

            max-width: 1000px;

            margin: auto;

        }


        /* =====================================================
           TOP ACTIONS
           ===================================================== */

        .top-actions {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 20px;

            flex-wrap: wrap;

        }


        .back {

            display: inline-block;

            text-decoration: none;

            color: #1570ef;

            font-weight: 600;

        }


        .pdf-button {

            display: inline-block;

            padding: 11px 18px;

            background: #172033;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-weight: 600;

            transition:
                background 0.2s ease,
                transform 0.2s ease;

        }


        .pdf-button:hover {

            background: #101828;

            transform:
                translateY(-1px);

        }


        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            margin-bottom: 20px;

            border:
                1px solid #e4e7ec;

            box-shadow:
                0 5px 18px
                rgba(16, 24, 40, 0.05);

        }


        h1 {

            margin-top: 0;

        }


        .details {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;

        }


        .detail-box {

            background: #f8f9fa;

            padding: 15px;

            border-radius: 8px;

        }


        .label {

            font-size: 13px;

            color: #667085;

            margin-bottom: 5px;

        }


        .value {

            font-weight: 600;

        }


        .table-wrapper {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 700px;

        }


        th,
        td {

            padding: 13px;

            border-bottom:
                1px solid #eaecf0;

            text-align: left;

        }


        th {

            background: #f9fafb;

            color: #475467;

            font-size: 13px;

        }


        .number {

            text-align: right;

        }


        .total {

            text-align: right;

            font-size: 21px;

            font-weight: bold;

            margin-top: 20px;

        }


        @media (max-width: 700px) {

            body {

                padding: 15px;

            }


            .details {

                grid-template-columns:
                    1fr;

            }


            .top-actions {

                align-items: flex-start;

            }


            .pdf-button {

                width: 100%;

                text-align: center;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         TOP ACTIONS
         ===================================================== -->

    <div class="top-actions">


        <a
            href="index.php"
            class="back"
        >
            ← Back to Sales
        </a>


        <a
            href="receipt.php?id=<?= (int) $sale['id'] ?>"
            class="pdf-button"
            target="_blank"
            rel="noopener noreferrer"
        >
            Generate PDF Receipt
        </a>


    </div>


    <!-- =====================================================
         SALE INFORMATION
         ===================================================== -->

    <div class="card">


        <h1>

            Sale #<?= (int) $sale['id'] ?>

        </h1>


        <div class="details">


            <div class="detail-box">

                <div class="label">

                    Customer

                </div>


                <div class="value">

                    <?= htmlspecialchars(
                        $sale['customer_name']
                        ?? 'Walk-in Customer'
                    ) ?>

                </div>

            </div>


            <div class="detail-box">

                <div class="label">

                    Recorded By

                </div>


                <div class="value">

                    <?= htmlspecialchars(
                        $sale['recorded_by']
                    ) ?>

                </div>

            </div>


            <div class="detail-box">

                <div class="label">

                    Customer Phone

                </div>


                <div class="value">

                    <?= htmlspecialchars(
                        $sale['customer_phone']
                        ?? '-'
                    ) ?>

                </div>

            </div>


            <div class="detail-box">

                <div class="label">

                    Sale Date

                </div>


                <div class="value">

                    <?= htmlspecialchars(
                        $sale['sale_date']
                    ) ?>

                </div>

            </div>


        </div>

    </div>


    <!-- =====================================================
         SOLD PRODUCTS
         ===================================================== -->

    <div class="card">


        <h2>

            Sold Products

        </h2>


        <div class="table-wrapper">


            <table>


                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            SKU
                        </th>

                        <th class="number">
                            Quantity
                        </th>

                        <th class="number">
                            Unit Price
                        </th>

                        <th class="number">
                            Subtotal
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (count($items) > 0): ?>


                        <?php foreach ($items as $item): ?>


                            <tr>


                                <td>

                                    <?= htmlspecialchars(
                                        $item['product_name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $item['sku']
                                    ) ?>

                                </td>


                                <td class="number">

                                    <?= (int)
                                        $item['quantity'] ?>

                                </td>


                                <td class="number">

                                    KSh

                                    <?= number_format(
                                        (float)
                                        $item['unit_price'],
                                        2
                                    ) ?>

                                </td>


                                <td class="number">

                                    KSh

                                    <?= number_format(
                                        (float)
                                        $item['subtotal'],
                                        2
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="5"
                                style="
                                    text-align:center;
                                    color:#667085;
                                "
                            >

                                No products found
                                for this sale.

                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>


            </table>


        </div>


        <div class="total">

            Total:

            KSh

            <?= number_format(
                (float)
                $sale['total_amount'],
                2
            ) ?>

        </div>


    </div>


</div>


</body>

</html>