<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$today = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

try {

    // Products
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM products
    ");

    $totalProducts = (int) $stmt->fetchColumn();


    // Customers
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM customers
    ");

    $totalCustomers = (int) $stmt->fetchColumn();


    // Suppliers
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM suppliers
    ");

    $totalSuppliers = (int) $stmt->fetchColumn();


    // Today's sales
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sales
        WHERE DATE(sale_date) = ?
    ");

    $stmt->execute([$today]);

    $todaySales = (int) $stmt->fetchColumn();


    // Today's revenue
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM sales
        WHERE DATE(sale_date) = ?
    ");

    $stmt->execute([$today]);

    $todayRevenue = (float) $stmt->fetchColumn();


    // Low stock
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE stock_quantity <= reorder_level
    ");

    $lowStockCount = (int) $stmt->fetchColumn();


    // Inventory value
    $stmt = $pdo->query("
        SELECT COALESCE(
            SUM(stock_quantity * buying_price),
            0
        )
        FROM products
    ");

    $inventoryValue = (float) $stmt->fetchColumn();


    // Total purchases
    $stmt = $pdo->query("
        SELECT COALESCE(
            SUM(total_amount),
            0
        )
        FROM purchases
    ");

    $totalPurchases = (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Recent sales
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            sales.id,
            sales.total_amount,
            sales.sale_date,
            customers.name AS customer_name,
            users.name AS user_name
        FROM sales

        LEFT JOIN customers
            ON sales.customer_id = customers.id

        INNER JOIN users
            ON sales.user_id = users.id

        ORDER BY sales.id DESC

        LIMIT 6
    ");

    $recentSales = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Low stock products
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            sku,
            stock_quantity,
            reorder_level
        FROM products
        WHERE stock_quantity <= reorder_level
        ORDER BY stock_quantity ASC
        LIMIT 6
    ");

    $lowStockProducts = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Seven-day sales chart
    |--------------------------------------------------------------------------
    */

    $chartLabels = [];
    $chartValues = [];

    for ($i = 6; $i >= 0; $i--) {

        $date = date(
            'Y-m-d',
            strtotime("-{$i} days")
        );

        $chartLabels[] = date(
            'D',
            strtotime($date)
        );

        $stmt = $pdo->prepare("
            SELECT COALESCE(
                SUM(total_amount),
                0
            )
            FROM sales
            WHERE DATE(sale_date) = ?
        ");

        $stmt->execute([$date]);

        $chartValues[] = (float) $stmt->fetchColumn();
    }


} catch (Throwable $e) {

    logError(
        'Dashboard failed to load.',
        $e
    );

    $totalProducts = 0;
    $totalCustomers = 0;
    $totalSuppliers = 0;
    $todaySales = 0;
    $todayRevenue = 0;
    $lowStockCount = 0;
    $inventoryValue = 0;
    $totalPurchases = 0;
    $recentSales = [];
    $lowStockProducts = [];
    $chartLabels = [];
    $chartValues = [];
}


/*
|--------------------------------------------------------------------------
| Prepare chart SVG
|--------------------------------------------------------------------------
*/

$chartWidth = 760;
$chartHeight = 260;

$chartPaddingLeft = 45;
$chartPaddingRight = 20;
$chartPaddingTop = 25;
$chartPaddingBottom = 35;

$maxChartValue = max(
    $chartValues ?: [0]
);

if ($maxChartValue <= 0) {
    $maxChartValue = 100;
}

$chartPoints = [];

$countValues = count($chartValues);

if ($countValues > 0) {

    foreach ($chartValues as $index => $value) {

        if ($countValues === 1) {
            $x = $chartWidth / 2;
        } else {
            $x =
                $chartPaddingLeft +
                (
                    $index /
                    ($countValues - 1)
                ) *
                (
                    $chartWidth -
                    $chartPaddingLeft -
                    $chartPaddingRight
                );
        }

        $y =
            $chartPaddingTop +
            (
                1 -
                ($value / $maxChartValue)
            ) *
            (
                $chartHeight -
                $chartPaddingTop -
                $chartPaddingBottom
            );

        $chartPoints[] = [
            'x' => $x,
            'y' => $y,
            'value' => $value
        ];
    }
}

$polylinePoints = '';

foreach ($chartPoints as $point) {

    $polylinePoints .=
        $point['x'] . ',' .
        $point['y'] . ' ';
}

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
    Dashboard | Softgenious Inventory
</title>


<style>

    * {
        box-sizing: border-box;
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
                #eef3f8,
                #f6f8fc
            );

        color: #253047;

        overflow-x: hidden;
    }


    /* ---------------------------------------------------------
       Ambient background
    --------------------------------------------------------- */

    .ambient {
        position: fixed;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        z-index: -1;
    }

    .ambient-one,
    .ambient-two,
    .ambient-three {
        position: absolute;
        border-radius: 50%;
        filter: blur(70px);
        opacity: .25;
    }

    .ambient-one {
        width: 300px;
        height: 300px;
        background: #dbeafe;
        top: -100px;
        right: 8%;
        animation: ambientMoveOne 12s ease-in-out infinite;
    }

    .ambient-two {
        width: 260px;
        height: 260px;
        background: #ede9fe;
        bottom: 5%;
        left: 20%;
        animation: ambientMoveTwo 15s ease-in-out infinite;
    }

    .ambient-three {
        width: 180px;
        height: 180px;
        background: #e0f2fe;
        top: 40%;
        right: 35%;
        animation: ambientMoveThree 11s ease-in-out infinite;
    }


    @keyframes ambientMoveOne {

        0%, 100% {
            transform: translate(0, 0);
        }

        50% {
            transform: translate(-35px, 30px);
        }

    }


    @keyframes ambientMoveTwo {

        0%, 100% {
            transform: translate(0, 0);
        }

        50% {
            transform: translate(30px, -35px);
        }

    }


    @keyframes ambientMoveThree {

        0%, 100% {
            transform: translate(0, 0);
        }

        50% {
            transform: translate(-25px, 25px);
        }

    }


    /* ---------------------------------------------------------
       Main content
    --------------------------------------------------------- */

    .main-content {
        margin-left: 260px;
        min-height: 100vh;
        padding: 28px;
    }


    .page-wrapper {
        max-width: 1500px;
        margin: 0 auto;
    }


    /* ---------------------------------------------------------
       Header
    --------------------------------------------------------- */

    .top-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 25px;
    }


    .header-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }


    .header-title h1 {
        margin: 0;
        color: #182238;
        font-size: 27px;
        font-weight: 800;
        letter-spacing: -.7px;
    }


    .header-title p {
        margin: 5px 0 0;
        color: #8490a3;
        font-size: 12px;
    }


    .header-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }


    .date-card {
        padding: 10px 14px;
        background: rgba(255,255,255,.82);
        border: 1px solid #e5eaf1;
        border-radius: 11px;
        color: #69758a;
        font-size: 11px;
        box-shadow: 0 7px 22px rgba(40,55,80,.04);
    }


    /* ---------------------------------------------------------
       Mobile menu
    --------------------------------------------------------- */

    .mobile-menu {
        display: none;
        width: 42px;
        height: 42px;
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 11px;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 4px;
        box-shadow: 0 5px 15px rgba(40,55,80,.05);
    }


    .mobile-menu span {
        width: 18px;
        height: 2px;
        border-radius: 3px;
        background: #536174;
    }


    /* ---------------------------------------------------------
       KPI cards
    --------------------------------------------------------- */

    .stats-grid {
        display: grid;
        grid-template-columns:
            repeat(6, minmax(0, 1fr));

        gap: 15px;
        margin-bottom: 20px;
    }


    .stat-card {
        position: relative;
        overflow: hidden;
        padding: 18px;
        background: rgba(255,255,255,.94);
        border: 1px solid #e6ebf1;
        border-radius: 16px;
        box-shadow:
            0 9px 30px rgba(35,55,80,.055);

        animation:
            cardEnter .55s ease both;

        transition:
            transform .25s ease,
            box-shadow .25s ease,
            border-color .25s ease;
    }


    .stat-card:nth-child(2) {
        animation-delay: .06s;
    }

    .stat-card:nth-child(3) {
        animation-delay: .12s;
    }

    .stat-card:nth-child(4) {
        animation-delay: .18s;
    }

    .stat-card:nth-child(5) {
        animation-delay: .24s;
    }

    .stat-card:nth-child(6) {
        animation-delay: .30s;
    }


    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow:
            0 18px 35px rgba(35,55,80,.11);
        border-color: #d9e2ef;
    }


    @keyframes cardEnter {

        from {
            opacity: 0;
            transform: translateY(15px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }

    }


    .stat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }


    .stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eef4ff;
        color: #3566c9;
    }


    .stat-icon svg {
        width: 20px;
        height: 20px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.7;
        stroke-linecap: round;
        stroke-linejoin: round;
    }


    .stat-label {
        color: #8994a6;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .7px;
    }


    .stat-number {
        color: #1d2940;
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -.7px;
    }


    .stat-card small {
        display: block;
        margin-top: 5px;
        color: #9aa4b3;
        font-size: 9px;
    }


    .stat-card.revenue .stat-icon {
        background: #edf9f2;
        color: #219653;
    }


    .stat-card.customers .stat-icon {
        background: #f2edff;
        color: #7046c8;
    }


    .stat-card.suppliers .stat-icon {
        background: #fff5e8;
        color: #c57a16;
    }


    .stat-card.sales .stat-icon {
        background: #eaf7ff;
        color: #1784b5;
    }


    .stat-card.warning .stat-icon {
        background: #fff0f0;
        color: #dc3545;
    }


    /* ---------------------------------------------------------
       Main dashboard grid
    --------------------------------------------------------- */

    .dashboard-grid {
        display: grid;
        grid-template-columns:
            minmax(0, 1.65fr)
            minmax(300px, .8fr);

        gap: 20px;
        margin-bottom: 20px;
    }


    .panel {
        background: rgba(255,255,255,.94);
        border: 1px solid #e5eaf1;
        border-radius: 17px;
        box-shadow:
            0 9px 30px rgba(35,55,80,.055);

        overflow: hidden;

        animation:
            panelEnter .65s ease both;
    }


    @keyframes panelEnter {

        from {
            opacity: 0;
            transform: translateY(12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }

    }


    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        border-bottom: 1px solid #edf1f5;
    }


    .panel-title {
        margin: 0;
        color: #27334a;
        font-size: 13px;
        font-weight: 800;
    }


    .panel-subtitle {
        margin: 4px 0 0;
        color: #9aa4b3;
        font-size: 10px;
    }


    .panel-link {
        color: #3263c4;
        text-decoration: none;
        font-size: 10px;
        font-weight: 700;
    }


    .panel-link:hover {
        text-decoration: underline;
    }


    /* ---------------------------------------------------------
       Chart
    --------------------------------------------------------- */

    .chart-container {
        padding: 15px 20px 10px;
        height: 310px;
    }


    .sales-chart {
        width: 100%;
        height: 100%;
        overflow: visible;
    }


    .chart-grid-line {
        stroke: #edf1f5;
        stroke-width: 1;
    }


    .chart-axis-label {
        fill: #9aa4b3;
        font-size: 9px;
    }


    .chart-line {
        fill: none;
        stroke: #4d70d8;
        stroke-width: 3;
        stroke-linecap: round;
        stroke-linejoin: round;

        stroke-dasharray: 1500;
        stroke-dashoffset: 1500;

        animation:
            drawChart 1.8s ease forwards .4s;
    }


    @keyframes drawChart {

        to {
            stroke-dashoffset: 0;
        }

    }


    .chart-point {
        fill: #fff;
        stroke: #4d70d8;
        stroke-width: 2;
        opacity: 0;
        animation:
            pointAppear .35s ease forwards;
    }


    .chart-point:nth-of-type(1) {
        animation-delay: .7s;
    }

    .chart-point:nth-of-type(2) {
        animation-delay: .8s;
    }

    .chart-point:nth-of-type(3) {
        animation-delay: .9s;
    }

    .chart-point:nth-of-type(4) {
        animation-delay: 1s;
    }

    .chart-point:nth-of-type(5) {
        animation-delay: 1.1s;
    }

    .chart-point:nth-of-type(6) {
        animation-delay: 1.2s;
    }

    .chart-point:nth-of-type(7) {
        animation-delay: 1.3s;
    }


    @keyframes pointAppear {

        to {
            opacity: 1;
        }

    }


    /* ---------------------------------------------------------
       Quick actions
    --------------------------------------------------------- */

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 20px;
    }


    .quick-action {
        min-height: 82px;
        padding: 14px;
        border: 1px solid #e8edf3;
        border-radius: 13px;
        background: #fafbfd;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 7px;
        transition: all .22s ease;
    }


    .quick-action:hover {
        transform: translateY(-3px);
        background: #f4f7fc;
        border-color: #d8e1ee;
        box-shadow: 0 8px 20px rgba(35,55,80,.06);
    }


    .quick-action strong {
        color: #35435b;
        font-size: 11px;
    }


    .quick-action span {
        color: #9aa4b3;
        font-size: 9px;
    }


    .quick-action-icon {
        width: 27px;
        height: 27px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #edf3ff;
        color: #3765bd;
    }


    .quick-action-icon svg {
        width: 15px;
        height: 15px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }


    /* ---------------------------------------------------------
       Recent sales
    --------------------------------------------------------- */

    .table-wrap {
        overflow-x: auto;
    }


    .sales-table {
        width: 100%;
        border-collapse: collapse;
    }


    .sales-table th {
        padding: 11px 20px;
        text-align: left;
        color: #98a2b1;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .6px;
        background: #fafbfd;
        border-bottom: 1px solid #edf1f5;
    }


    .sales-table td {
        padding: 13px 20px;
        color: #59667b;
        font-size: 10px;
        border-bottom: 1px solid #f0f3f7;
    }


    .sales-table tr:last-child td {
        border-bottom: none;
    }


    .sales-table tr {
        transition: background .18s ease;
    }


    .sales-table tbody tr:hover {
        background: #fafbfd;
    }


    .sale-id {
        color: #315ec0;
        font-weight: 800;
    }


    .sale-amount {
        color: #25334a;
        font-weight: 800;
    }


    .customer-name {
        color: #4c5b71;
        font-weight: 600;
    }


    .sale-date {
        color: #929cab;
    }


    .empty-state {
        padding: 35px 20px;
        text-align: center;
        color: #9ba5b4;
        font-size: 11px;
    }


    /* ---------------------------------------------------------
       Stock alerts
    --------------------------------------------------------- */

    .stock-list {
        padding: 7px 20px 12px;
    }


    .stock-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid #edf1f5;
    }


    .stock-item:last-child {
        border-bottom: none;
    }


    .stock-product {
        min-width: 0;
    }


    .stock-product strong {
        display: block;
        color: #46546a;
        font-size: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }


    .stock-product span {
        display: block;
        margin-top: 3px;
        color: #a0a8b5;
        font-size: 9px;
    }


    .stock-number {
        min-width: 45px;
        padding: 6px 8px;
        border-radius: 7px;
        background: #fff0f0;
        color: #d63b4c;
        text-align: center;
        font-size: 10px;
        font-weight: 800;
        animation: stockPulse 2.3s ease-in-out infinite;
    }


    @keyframes stockPulse {

        0%, 100% {
            box-shadow: 0 0 0 0 rgba(214,59,76,0);
        }

        50% {
            box-shadow: 0 0 0 4px rgba(214,59,76,.07);
        }

    }


    /* ---------------------------------------------------------
       Bottom cards
    --------------------------------------------------------- */

    .bottom-grid {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 20px;
    }


    .summary-card {
        padding: 21px;
        background: rgba(255,255,255,.94);
        border: 1px solid #e5eaf1;
        border-radius: 17px;
        box-shadow:
            0 9px 30px rgba(35,55,80,.055);

        transition: transform .25s ease,
                    box-shadow .25s ease;
    }


    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow:
            0 16px 32px rgba(35,55,80,.09);
    }


    .summary-label {
        color: #8e99aa;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .7px;
    }


    .summary-value {
        margin-top: 7px;
        color: #1f2b42;
        font-size: 25px;
        font-weight: 800;
        letter-spacing: -.7px;
    }


    .summary-description {
        margin-top: 5px;
        color: #a0a8b5;
        font-size: 9px;
    }


    /* ---------------------------------------------------------
       Responsive
    --------------------------------------------------------- */

    @media (max-width: 1250px) {

        .stats-grid {
            grid-template-columns:
                repeat(3, 1fr);
        }

    }


    @media (max-width: 1000px) {

        .dashboard-grid {
            grid-template-columns: 1fr;
        }

    }


    @media (max-width: 900px) {

        .main-content {
            margin-left: 0;
            padding: 20px;
        }

        .mobile-menu {
            display: flex;
        }

        .date-card {
            display: none;
        }

        .top-header {
            align-items: flex-start;
        }

    }


    @media (max-width: 650px) {

        .main-content {
            padding: 15px;
        }

        .stats-grid {
            grid-template-columns:
                repeat(2, 1fr);
            gap: 10px;
        }

        .stat-card {
            padding: 15px;
        }

        .stat-number {
            font-size: 21px;
        }

        .header-title h1 {
            font-size: 22px;
        }

        .bottom-grid {
            grid-template-columns: 1fr;
        }

        .quick-actions {
            grid-template-columns: 1fr 1fr;
            padding: 15px;
        }

        .chart-container {
            padding: 10px;
            height: 250px;
        }

    }


    @media (max-width: 430px) {

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .quick-actions {
            grid-template-columns: 1fr;
        }

        .panel-header {
            padding: 15px;
        }

    }

</style>

</head>

<body>

<div class="ambient">


<div class="ambient-one"></div>
<div class="ambient-two"></div>
<div class="ambient-three"></div>

</div>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

<div class="page-wrapper">


    <!-- HEADER -->

    <header class="top-header">

        <div class="header-left">

            <button
                type="button"
                class="mobile-menu"
                id="mobileMenu"
                aria-label="Open navigation"
            >

                <span></span>
                <span></span>
                <span></span>

            </button>


            <div class="header-title">

                <h1>
                    Dashboard
                </h1>

                <p>
                    Overview of your inventory and sales activity.
                </p>

            </div>

        </div>


        <div class="header-right">

            <div class="date-card">

                <?= date('l, d M Y') ?>

            </div>

        </div>

    </header>


    <!-- KPI CARDS -->

    <section class="stats-grid">


        <!-- PRODUCTS -->

        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-label">
                    Products
                </div>

                <div class="stat-icon">

                    <svg viewBox="0 0 24 24">
                        <path d="M20 7.5 12 3 4 7.5v9L12 21l8-4.5v-9z"/>
                        <path d="M12 3v9m8-4.5-8 4.5-8-4.5"/>
                    </svg>

                </div>

            </div>

            <div
                class="stat-number counter"
                data-value="<?= $totalProducts ?>"
            >
                0
            </div>

            <small>
                Total products
            </small>

        </div>


        <!-- CUSTOMERS -->

        <div class="stat-card customers">

            <div class="stat-top">

                <div class="stat-label">
                    Customers
                </div>

                <div class="stat-icon">

                    <svg viewBox="0 0 24 24">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>

                </div>

            </div>

            <div
                class="stat-number counter"
                data-value="<?= $totalCustomers ?>"
            >
                0
            </div>

            <small>
                Registered customers
            </small>

        </div>


        <!-- SUPPLIERS -->

        <div class="stat-card suppliers">

            <div class="stat-top">

                <div class="stat-label">
                    Suppliers
                </div>

                <div class="stat-icon">

                    <svg viewBox="0 0 24 24">
                        <path d="M3 21V8l9-5 9 5v13H3z"/>
                        <path d="M7 21v-6h10v6"/>
                        <path d="M8 9h2m4 0h2"/>
                    </svg>

                </div>

            </div>

            <div
                class="stat-number counter"
                data-value="<?= $totalSuppliers ?>"
            >
                0
            </div>

            <small>
                Active suppliers
            </small>

        </div>


        <!-- TODAY'S SALES -->

        <div class="stat-card sales">

            <div class="stat-top">

                <div class="stat-label">
                    Today's Sales
                </div>

                <div class="stat-icon">

                    <svg viewBox="0 0 24 24">
                        <path d="M6 2h12v20H6V2z"/>
                        <path d="M9 6h6m-6 4h6m-6 4h4"/>
                    </svg>

                </div>

            </div>

            <div
                class="stat-number counter"
                data-value="<?= $todaySales ?>"
            >
                0
            </div>

            <small>
                Sales recorded today
            </small>

        </div>


        <!-- REVENUE -->

        <div class="stat-card revenue">

            <div class="stat-top">

                <div class="stat-label">
                    Today's Revenue
                </div>

                <div class="stat-icon">

                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M15 8.5c-.7-.6-1.7-1-3-1-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2c-1.3 0-2.3-.4-3-1"/>
                        <path d="M12 6v2m0 8v2"/>
                    </svg>

                </div>

            </div>

            <div class="stat-number">

                KSh
                <span
                    class="counter"
                    data-value="<?= $todayRevenue ?>"
                    data-decimal="2"
                >
                    0
                </span>

            </div>

            <small>
                Revenue generated today
            </small>

        </div>


        <!-- LOW STOCK -->

        <div class="stat-card warning">

            <div class="stat-top">

                <div class="stat-label">
                    Low Stock
                </div>

                <div class="stat-icon">

                    <svg viewBox="0 0 24 24">
                        <path d="M12 3 2 21h20L12 3z"/>
                        <path d="M12 9v5m0 3v1"/>
                    </svg>

                </div>

            </div>

            <div
                class="stat-number counter"
                data-value="<?= $lowStockCount ?>"
            >
                0
            </div>

            <small>
                Products need attention
            </small>

        </div>

    </section>


    <!-- CHART + QUICK ACTIONS -->

    <section class="dashboard-grid">


        <!-- SALES CHART -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <h2 class="panel-title">
                        Sales Overview
                    </h2>

                    <p class="panel-subtitle">
                        Revenue generated over the last 7 days
                    </p>

                </div>

                <a
                    href="reports/index.php"
                    class="panel-link"
                >
                    View Reports
                </a>

            </div>


            <div class="chart-container">

                <svg
                    class="sales-chart"
                    viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>"
                    preserveAspectRatio="none"
                >


                    <!-- GRID -->

                    <?php for ($i = 0; $i <= 4; $i++): ?>

                        <?php

                        $y =
                            $chartPaddingTop +
                            (
                                $i / 4
                            ) *
                            (
                                $chartHeight -
                                $chartPaddingTop -
                                $chartPaddingBottom
                            );

                        ?>

                        <line
                            class="chart-grid-line"
                            x1="<?= $chartPaddingLeft ?>"
                            y1="<?= $y ?>"
                            x2="<?= $chartWidth - $chartPaddingRight ?>"
                            y2="<?= $y ?>"
                        />

                    <?php endfor; ?>


                    <!-- Y AXIS -->

                    <?php for ($i = 0; $i <= 4; $i++): ?>

                        <?php

                        $labelValue =
                            $maxChartValue -
                            (
                                $i / 4
                            ) *
                            $maxChartValue;

                        $y =
                            $chartPaddingTop +
                            (
                                $i / 4
                            ) *
                            (
                                $chartHeight -
                                $chartPaddingTop -
                                $chartPaddingBottom
                            );

                        ?>

                        <text
                            class="chart-axis-label"
                            x="4"
                            y="<?= $y + 3 ?>"
                        >
                            <?= number_format($labelValue, 0) ?>
                        </text>

                    <?php endfor; ?>


                    <!-- LINE -->

                    <?php if (!empty($polylinePoints)): ?>

                        <polyline
                            class="chart-line"
                            points="<?= htmlspecialchars(trim($polylinePoints)) ?>"
                        />

                    <?php endif; ?>


                    <!-- POINTS -->

                    <?php foreach ($chartPoints as $index => $point): ?>

                        <circle
                            class="chart-point"
                            cx="<?= $point['x'] ?>"
                            cy="<?= $point['y'] ?>"
                            r="4"
                        />

                        <text
                            class="chart-axis-label"
                            x="<?= $point['x'] ?>"
                            y="<?= $chartHeight - 10 ?>"
                            text-anchor="middle"
                        >
                            <?= htmlspecialchars($chartLabels[$index] ?? '') ?>
                        </text>

                    <?php endforeach; ?>


                </svg>

            </div>

        </div>


        <!-- QUICK ACTIONS -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <h2 class="panel-title">
                        Quick Actions
                    </h2>

                    <p class="panel-subtitle">
                        Common tasks
                    </p>

                </div>

            </div>


            <div class="quick-actions">


                <a
                    href="products/add.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <svg viewBox="0 0 24 24">
                            <path d="M12 5v14m-7-7h14"/>
                        </svg>

                    </div>

                    <strong>
                        Add Product
                    </strong>

                    <span>
                        Create inventory item
                    </span>

                </a>


                <a
                    href="sales/add.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <svg viewBox="0 0 24 24">
                            <path d="M6 2h12v20H6V2z"/>
                            <path d="M9 6h6m-6 4h6"/>
                        </svg>

                    </div>

                    <strong>
                        New Sale
                    </strong>

                    <span>
                        Record a sale
                    </span>

                </a>


                <a
                    href="purchases/add.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <svg viewBox="0 0 24 24">
                            <path d="M3 6h18M5 6l1 14h12l1-14"/>
                            <path d="M9 6V4h6v2"/>
                        </svg>

                    </div>

                    <strong>
                        Add Purchase
                    </strong>

                    <span>
                        Receive stock
                    </span>

                </a>


                <a
                    href="reports/index.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <svg viewBox="0 0 24 24">
                            <path d="M4 19V5m0 14h17"/>
                            <path d="M8 16v-5m4 5V7m4 9v-8m4 8V4"/>
                        </svg>

                    </div>

                    <strong>
                        View Reports
                    </strong>

                    <span>
                        Analyse performance
                    </span>

                </a>

            </div>

        </div>

    </section>


    <!-- RECENT SALES + STOCK ALERTS -->

    <section class="dashboard-grid">


        <!-- RECENT SALES -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <h2 class="panel-title">
                        Recent Sales
                    </h2>

                    <p class="panel-subtitle">
                        Latest transactions
                    </p>

                </div>

                <a
                    href="sales/index.php"
                    class="panel-link"
                >
                    View All
                </a>

            </div>


            <?php if (!empty($recentSales)): ?>

                <div class="table-wrap">

                    <table class="sales-table">

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Recorded By
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Date
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($recentSales as $sale): ?>

                                <tr>

                                    <td>

                                        <span class="sale-id">

                                            #<?= (int) $sale['id'] ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="customer-name">

                                            <?= htmlspecialchars(
                                                $sale['customer_name']
                                                    ?: 'Walk-in Customer'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $sale['user_name']
                                        ) ?>

                                    </td>


                                    <td>

                                        <span class="sale-amount">

                                            KSh
                                            <?= number_format(
                                                (float) $sale['total_amount'],
                                                2
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="sale-date">

                                            <?= date(
                                                'd M Y',
                                                strtotime($sale['sale_date'])
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    No sales have been recorded yet.

                </div>

            <?php endif; ?>

        </div>


        <!-- STOCK ALERTS -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <h2 class="panel-title">
                        Stock Alerts
                    </h2>

                    <p class="panel-subtitle">
                        Products requiring attention
                    </p>

                </div>

                <a
                    href="products/index.php"
                    class="panel-link"
                >
                    View Products
                </a>

            </div>


            <div class="stock-list">


                <?php if (!empty($lowStockProducts)): ?>

                    <?php foreach ($lowStockProducts as $product): ?>

                        <div class="stock-item">

                            <div class="stock-product">

                                <strong>

                                    <?= htmlspecialchars(
                                        $product['name']
                                    ) ?>

                                </strong>

                                <span>

                                    SKU:
                                    <?= htmlspecialchars(
                                        $product['sku']
                                    ) ?>

                                </span>

                            </div>


                            <div class="stock-number">

                                <?= (int) $product['stock_quantity'] ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">

                        All products are sufficiently stocked.

                    </div>

                <?php endif; ?>


            </div>

        </div>

    </section>


    <!-- SUMMARY -->

    <section class="bottom-grid">


        <div class="summary-card">

            <div class="summary-label">
                Inventory Value
            </div>

            <div class="summary-value">

                KSh
                <span
                    class="counter"
                    data-value="<?= $inventoryValue ?>"
                    data-decimal="2"
                >
                    0
                </span>

            </div>

            <div class="summary-description">
                Estimated value based on current buying prices.
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Total Purchases
            </div>

            <div class="summary-value">

                KSh
                <span
                    class="counter"
                    data-value="<?= $totalPurchases ?>"
                    data-decimal="2"
                >
                    0
                </span>

            </div>

            <div class="summary-description">
                Total recorded purchase value.
            </div>

        </div>


    </section>


</div>

</main>

<script>

/*
|--------------------------------------------------------------------------
| Animated counters
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const counters =
            document.querySelectorAll('.counter');


        counters.forEach(function (counter) {

            const target =
                parseFloat(
                    counter.dataset.value || 0
                );

            const decimal =
                parseInt(
                    counter.dataset.decimal || 0
                );


            const duration = 1100;

            const startTime =
                performance.now();


            function updateCounter(currentTime) {

                const elapsed =
                    currentTime - startTime;

                const progress =
                    Math.min(
                        elapsed / duration,
                        1
                    );


                const eased =
                    1 -
                    Math.pow(
                        1 - progress,
                        3
                    );


                const current =
                    target * eased;


                counter.textContent =
                    current.toLocaleString(
                        'en-KE',
                        {
                            minimumFractionDigits: decimal,
                            maximumFractionDigits: decimal
                        }
                    );


                if (progress < 1) {

                    requestAnimationFrame(
                        updateCounter
                    );

                }

            }


            requestAnimationFrame(
                updateCounter
            );

        });

    }
);

</script>

</body>

</html>
