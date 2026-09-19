<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Validate Date Filters
|--------------------------------------------------------------------------
*/

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

/*
|--------------------------------------------------------------------------
| Sales Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS transaction_count,
        COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
");

$stmt->execute([$from, $to]);

$sales_summary = $stmt->fetch();

$transaction_count = (int) ($sales_summary['transaction_count'] ?? 0);
$total_revenue     = (float) ($sales_summary['total_revenue'] ?? 0);

/*
|--------------------------------------------------------------------------
| Top Selling Products
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        products.name AS product_name,
        products.sku,
        SUM(sale_items.quantity) AS quantity_sold,
        SUM(sale_items.subtotal) AS revenue
    FROM sale_items

    INNER JOIN sales
        ON sale_items.sale_id = sales.id

    INNER JOIN products
        ON sale_items.product_id = products.id

    WHERE DATE(sales.sale_date) BETWEEN ? AND ?

    GROUP BY
        products.id,
        products.name,
        products.sku

    ORDER BY quantity_sold DESC
");

$stmt->execute([$from, $to]);

$top_products = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Stock Report
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        products.id,
        products.name,
        products.sku,
        products.category,
        products.stock_quantity,
        products.reorder_level,
        products.buying_price,
        products.selling_price,

        (
            products.stock_quantity * products.buying_price
        ) AS stock_buying_value,

        (
            products.stock_quantity * products.selling_price
        ) AS stock_selling_value,

        suppliers.name AS supplier_name

    FROM products

    LEFT JOIN suppliers
        ON products.supplier_id = suppliers.id

    ORDER BY products.stock_quantity ASC
");

$stock_report = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Stock Totals
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT

        COALESCE(
            SUM(stock_quantity * buying_price),
            0
        ) AS total_buying_value,

        COALESCE(
            SUM(stock_quantity * selling_price),
            0
        ) AS total_selling_value,

        COALESCE(
            SUM(
                CASE
                    WHEN stock_quantity <= reorder_level
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS low_stock_count

    FROM products
");

$stock_summary = $stmt->fetch();

$total_buying_value  = (float) ($stock_summary['total_buying_value'] ?? 0);
$total_selling_value = (float) ($stock_summary['total_selling_value'] ?? 0);
$low_stock_count     = (int) ($stock_summary['low_stock_count'] ?? 0);

$potential_margin = $total_selling_value - $total_buying_value;

/*
|--------------------------------------------------------------------------
| Additional Statistics
|--------------------------------------------------------------------------
*/

$total_units = 0;

foreach ($stock_report as $product) {
    $total_units += (int) $product['stock_quantity'];
}

$average_sale = $transaction_count > 0
    ? $total_revenue / $transaction_count
    : 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reports - SoftgeniousDev</title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --text: #172033;
            --muted: #64748b;
            --border: #e5eaf1;
            --card: #ffffff;
            --background: #f5f8fc;
            --success: #16a34a;
            --danger: #dc2626;
        }

        body {
            margin: 0;
            font-family:
                Inter,
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f7faff 0%,
                    #eef4fb 100%
                );

            color: var(--text);
            min-height: 100vh;
        }

        /*
        |--------------------------------------------------------------------------
        | Main Layout
        |--------------------------------------------------------------------------
        */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        .ambient {
            position: fixed;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.20;
            pointer-events: none;
            z-index: 0;
        }

        .ambient.one {
            top: -180px;
            right: 80px;
            background: #60a5fa;
            animation: floatOne 12s ease-in-out infinite;
        }

        .ambient.two {
            bottom: -220px;
            left: 300px;
            background: #a78bfa;
            animation: floatTwo 15s ease-in-out infinite;
        }

        @keyframes floatOne {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(-30px, 35px);
            }
        }

        @keyframes floatTwo {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(35px, -25px);
            }
        }

        .content {
            position: relative;
            z-index: 1;
            padding: 32px;
            max-width: 1600px;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 28px;
        }

        .title-area h1 {
            margin: 0;
            font-size: 30px;
            letter-spacing: -0.7px;
        }

        .title-area p {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            box-shadow: 0 5px 18px rgba(15, 23, 42, 0.05);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.12);
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Card
        |--------------------------------------------------------------------------
        */

        .filter-card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow:
                0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .filter-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .filter-title {
            font-size: 15px;
            font-weight: 800;
        }

        .filter-description {
            color: var(--muted);
            font-size: 13px;
        }

        .filters {
            display: flex;
            align-items: end;
            gap: 15px;
            flex-wrap: wrap;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field label {
            font-size: 12px;
            font-weight: 800;
            color: #475569;
        }

        .field input {
            height: 42px;
            min-width: 190px;
            padding: 0 12px;
            border: 1px solid #d8dee8;
            border-radius: 10px;
            background: #ffffff;
            color: #1e293b;
            outline: none;
            transition: 0.2s;
        }

        .field input:focus {
            border-color: #60a5fa;
            box-shadow:
                0 0 0 4px rgba(37, 99, 235, 0.09);
        }

        .generate-button {
            height: 42px;
            padding: 0 19px;
            border: none;
            border-radius: 10px;
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );
            color: #ffffff;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
            box-shadow:
                0 8px 18px rgba(37, 99, 235, 0.20);
        }

        .generate-button:hover {
            transform: translateY(-1px);
            box-shadow:
                0 11px 24px rgba(37, 99, 235, 0.27);
        }

        /*
        |--------------------------------------------------------------------------
        | Summary Cards
        |--------------------------------------------------------------------------
        */

        .cards {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .card {
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: 20px;
            box-shadow:
                0 8px 25px rgba(15, 23, 42, 0.05);
            transition:
                transform 0.22s ease,
                box-shadow 0.22s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow:
                0 16px 34px rgba(15, 23, 42, 0.09);
        }

        .card-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        .card-value {
            font-size: 25px;
            font-weight: 850;
            letter-spacing: -0.5px;
        }

        .card-subtitle {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Sections
        |--------------------------------------------------------------------------
        */

        .section {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow:
                0 8px 25px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: -0.3px;
        }

        .section-header p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .section-count {
            padding: 7px 10px;
            border-radius: 9px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 12px;
            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | Tables
        |--------------------------------------------------------------------------
        */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #edf0f5;
            border-radius: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            padding: 13px 14px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 14px;
            border-top: 1px solid #edf0f5;
            font-size: 13px;
            color: #334155;
        }

        tbody tr {
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        .product-name {
            font-weight: 800;
            color: #1e293b;
        }

        .sku {
            font-family: Consolas, monospace;
            font-size: 12px;
            color: #64748b;
        }

        .money {
            font-weight: 750;
            white-space: nowrap;
        }

        .stock-number {
            font-weight: 850;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 6px 9px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
        }

        .status.low {
            color: #b91c1c;
            background: #fef2f2;
        }

        .status.normal {
            color: #15803d;
            background: #f0fdf4;
        }

        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */

        .empty {
            padding: 35px 15px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | Valuation
        |--------------------------------------------------------------------------
        */

        .valuation-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .valuation-item {
            border: 1px solid #e8edf4;
            background: #f8fafc;
            border-radius: 14px;
            padding: 18px;
        }

        .valuation-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .valuation-value {
            font-size: 20px;
            font-weight: 850;
            color: #172033;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1200px) {

            .cards {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .valuation-grid {
                grid-template-columns:
                    1fr;
            }

        }

        @media (max-width: 850px) {

            .main-content {
                margin-left: 0;
            }

            .content {
                padding: 22px 16px;
            }

            .page-header {
                flex-direction: column;
            }

            .filters {
                align-items: stretch;
            }

            .field {
                width: 100%;
            }

            .field input {
                width: 100%;
                min-width: 0;
            }

            .generate-button {
                width: 100%;
            }

        }

        @media (max-width: 600px) {

            .cards {
                grid-template-columns: 1fr;
            }

            .title-area h1 {
                font-size: 25px;
            }

            .filter-card,
            .section {
                padding: 16px;
                border-radius: 14px;
            }

            .section-header {
                align-items: flex-start;
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

    <div class="main-content">

        <div class="ambient one"></div>
        <div class="ambient two"></div>

        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="content">

            <!-- Header -->

            <div class="page-header">

                <div class="title-area">

                    <h1>Reports & Analytics</h1>

                    <p>
                        Sales performance, inventory valuation and
                        stock intelligence.
                    </p>

                </div>

                <div class="header-badge">

                    <span class="status-dot"></span>

                    Reporting System Online

                </div>

            </div>

            <!-- Date Filters -->

            <div class="filter-card">

                <div class="filter-top">

                    <div>

                        <div class="filter-title">
                            Report Period
                        </div>

                        <div class="filter-description">
                            Select the period used for sales analytics.
                        </div>

                    </div>

                </div>

                <form
                    method="GET"
                    class="filters"
                >

                    <div class="field">

                        <label for="from">
                            From
                        </label>

                        <input
                            type="date"
                            id="from"
                            name="from"
                            value="<?= htmlspecialchars($from) ?>"
                        >

                    </div>

                    <div class="field">

                        <label for="to">
                            To
                        </label>

                        <input
                            type="date"
                            id="to"
                            name="to"
                            value="<?= htmlspecialchars($to) ?>"
                        >

                    </div>

                    <button
                        type="submit"
                        class="generate-button"
                    >
                        Generate Report
                    </button>

                </form>

            </div>

            <!-- Summary Cards -->

            <div class="cards">

                <div class="card">

                    <div class="card-label">
                        Transactions
                    </div>

                    <div class="card-value">
                        <?= number_format($transaction_count) ?>
                    </div>

                    <div class="card-subtitle">
                        Sales in selected period
                    </div>

                </div>

                <div class="card">

                    <div class="card-label">
                        Revenue
                    </div>

                    <div class="card-value">
                        KSh <?= number_format(
                            $total_revenue,
                            2
                        ) ?>
                    </div>

                    <div class="card-subtitle">
                        Total sales revenue
                    </div>

                </div>

                <div class="card">

                    <div class="card-label">
                        Stock Buying Value
                    </div>

                    <div class="card-value">
                        KSh <?= number_format(
                            $total_buying_value,
                            2
                        ) ?>
                    </div>

                    <div class="card-subtitle">
                        Current inventory cost
                    </div>

                </div>

                <div class="card">

                    <div class="card-label">
                        Low Stock
                    </div>

                    <div class="card-value">
                        <?= number_format($low_stock_count) ?>
                    </div>

                    <div class="card-subtitle">
                        Products at reorder level
                    </div>

                </div>

            </div>

            <!-- Secondary Statistics -->

            <div class="cards">

                <div class="card">

                    <div class="card-label">
                        Average Sale
                    </div>

                    <div class="card-value">
                        KSh <?= number_format(
                            $average_sale,
                            2
                        ) ?>
                    </div>

                    <div class="card-subtitle">
                        Average transaction value
                    </div>

                </div>

                <div class="card">

                    <div class="card-label">
                        Stock Units
                    </div>

                    <div class="card-value">
                        <?= number_format($total_units) ?>
                    </div>

                    <div class="card-subtitle">
                        Units currently in inventory
                    </div>

                </div>

                <div class="card">

                    <div class="card-label">
                        Selling Value
                    </div>

                    <div class="card-value">
                        KSh <?= number_format(
                            $total_selling_value,
                            2
                        ) ?>
                    </div>

                    <div class="card-subtitle">
                        Potential inventory sales value
                    </div>

                </div>

                <div class="card">

                    <div class="card-label">
                        Potential Margin
                    </div>

                    <div class="card-value">
                        KSh <?= number_format(
                            $potential_margin,
                            2
                        ) ?>
                    </div>

                    <div class="card-subtitle">
                        Selling value minus buying value
                    </div>

                </div>

            </div>

            <!-- Top Products -->

            <section class="section">

                <div class="section-header">

                    <div>

                        <h2>
                            Top Selling Products
                        </h2>

                        <p>
                            Products ranked by quantity sold.
                        </p>

                    </div>

                    <div class="section-count">
                        <?= number_format(count($top_products)) ?>
                        products
                    </div>

                </div>

                <?php if (!empty($top_products)): ?>

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

                                    <th>
                                        Quantity Sold
                                    </th>

                                    <th>
                                        Revenue
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($top_products as $product): ?>

                                    <tr>

                                        <td>

                                            <div class="product-name">
                                                <?= htmlspecialchars(
                                                    $product['product_name']
                                                ) ?>
                                            </div>

                                        </td>

                                        <td>

                                            <span class="sku">
                                                <?= htmlspecialchars(
                                                    $product['sku']
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <strong>
                                                <?= number_format(
                                                    (int) $product['quantity_sold']
                                                ) ?>
                                            </strong>

                                        </td>

                                        <td>

                                            <span class="money">
                                                KSh
                                                <?= number_format(
                                                    (float) $product['revenue'],
                                                    2
                                                ) ?>
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty">

                        No sales found for the selected date range.

                    </div>

                <?php endif; ?>

            </section>

            <!-- Current Stock -->

            <section class="section">

                <div class="section-header">

                    <div>

                        <h2>
                            Current Stock Report
                        </h2>

                        <p>
                            Current inventory position and valuation.
                        </p>

                    </div>

                    <div class="section-count">
                        <?= number_format(count($stock_report)) ?>
                        products
                    </div>

                </div>

                <?php if (!empty($stock_report)): ?>

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

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Supplier
                                    </th>

                                    <th>
                                        Stock
                                    </th>

                                    <th>
                                        Buying Value
                                    </th>

                                    <th>
                                        Selling Value
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($stock_report as $product): ?>

                                    <?php

                                    $stock_quantity =
                                        (int) $product['stock_quantity'];

                                    $reorder_level =
                                        (int) $product['reorder_level'];

                                    $is_low =
                                        $stock_quantity <= $reorder_level;

                                    ?>

                                    <tr>

                                        <td>

                                            <div class="product-name">
                                                <?= htmlspecialchars(
                                                    $product['name']
                                                ) ?>
                                            </div>

                                        </td>

                                        <td>

                                            <span class="sku">
                                                <?= htmlspecialchars(
                                                    $product['sku']
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $product['category'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $product['supplier_name'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="stock-number">
                                                <?= number_format(
                                                    $stock_quantity
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span class="money">
                                                KSh
                                                <?= number_format(
                                                    (float) $product['stock_buying_value'],
                                                    2
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span class="money">
                                                KSh
                                                <?= number_format(
                                                    (float) $product['stock_selling_value'],
                                                    2
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <?php if ($is_low): ?>

                                                <span class="status low">
                                                    Low Stock
                                                </span>

                                            <?php else: ?>

                                                <span class="status normal">
                                                    Normal
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty">
                        No products found in inventory.
                    </div>

                <?php endif; ?>

            </section>

            <!-- Inventory Valuation -->

            <section class="section">

                <div class="section-header">

                    <div>

                        <h2>
                            Inventory Valuation
                        </h2>

                        <p>
                            Estimated current value of available stock.
                        </p>

                    </div>

                </div>

                <div class="valuation-grid">

                    <div class="valuation-item">

                        <div class="valuation-label">
                            Current Buying Value
                        </div>

                        <div class="valuation-value">
                            KSh <?= number_format(
                                $total_buying_value,
                                2
                            ) ?>
                        </div>

                    </div>

                    <div class="valuation-item">

                        <div class="valuation-label">
                            Current Selling Value
                        </div>

                        <div class="valuation-value">
                            KSh <?= number_format(
                                $total_selling_value,
                                2
                            ) ?>
                        </div>

                    </div>

                    <div class="valuation-item">

                        <div class="valuation-label">
                            Potential Gross Margin
                        </div>

                        <div class="valuation-value">
                            KSh <?= number_format(
                                $potential_margin,
                                2
                            ) ?>
                        </div>

                    </div>

                </div>

            </section>

        </main>

    </div>

</body>

</html>
