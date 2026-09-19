<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

try {

    /*
    |--------------------------------------------------------------------------
    | Sales list
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            sales.id,
            customers.name AS customer_name,
            users.name AS user_name,
            sales.total_amount,
            sales.sale_date,
            COUNT(sale_items.id) AS item_count
        FROM sales

        LEFT JOIN customers
            ON sales.customer_id = customers.id

        INNER JOIN users
            ON sales.user_id = users.id

        LEFT JOIN sale_items
            ON sales.id = sale_items.sale_id

        GROUP BY
            sales.id,
            customers.name,
            users.name,
            sales.total_amount,
            sales.sale_date

        ORDER BY sales.id DESC
    ");

    $sales = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Sales statistics
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_sales,
            COALESCE(SUM(total_amount), 0) AS total_revenue,
            COALESCE(AVG(total_amount), 0) AS average_sale
        FROM sales
    ");

    $stats = $stmt->fetch();


    $totalSales =
        (int) ($stats['total_sales'] ?? 0);

    $totalRevenue =
        (float) ($stats['total_revenue'] ?? 0);

    $averageSale =
        (float) ($stats['average_sale'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Today's statistics
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS today_sales,
            COALESCE(SUM(total_amount), 0) AS today_revenue
        FROM sales
        WHERE DATE(sale_date) = CURDATE()
    ");

    $todayStats = $stmt->fetch();


    $todaySales =
        (int) ($todayStats['today_sales'] ?? 0);

    $todayRevenue =
        (float) ($todayStats['today_revenue'] ?? 0);


} catch (Throwable $e) {

    logError(
        'Sales page failed to load.',
        $e
    );

    $sales = [];

    $totalSales = 0;
    $totalRevenue = 0;
    $averageSale = 0;
    $todaySales = 0;
    $todayRevenue = 0;

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
    Sales | SoftgeniousDev
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


    /* =========================================================
       AMBIENT BACKGROUND
    ========================================================= */

    .ambient {
        position: fixed;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        z-index: -1;
    }


    .ambient-one,
    .ambient-two {
        position: absolute;
        border-radius: 50%;
        filter: blur(75px);
        opacity: .20;
    }


    .ambient-one {
        width: 310px;
        height: 310px;
        background: #dbeafe;
        top: -120px;
        right: 10%;

        animation:
            floatOne 12s ease-in-out infinite;
    }


    .ambient-two {
        width: 250px;
        height: 250px;
        background: #ede9fe;
        bottom: 5%;
        left: 20%;

        animation:
            floatTwo 15s ease-in-out infinite;
    }


    @keyframes floatOne {

        0%, 100% {
            transform: translate(0, 0);
        }

        50% {
            transform: translate(-35px, 30px);
        }

    }


    @keyframes floatTwo {

        0%, 100% {
            transform: translate(0, 0);
        }

        50% {
            transform: translate(35px, -25px);
        }

    }


    /* =========================================================
       MAIN
    ========================================================= */

    .main-content {
        margin-left: 260px;
        min-height: 100vh;
        padding: 28px;
    }


    .page-wrapper {
        max-width: 1500px;
        margin: 0 auto;
    }


    /* =========================================================
       HEADER
    ========================================================= */

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }


    .header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }


    .mobile-menu {
        display: none;
        width: 42px;
        height: 42px;
        border: 1px solid #e1e7ef;
        background: #fff;
        border-radius: 11px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 4px;
        cursor: pointer;
    }


    .mobile-menu span {
        width: 18px;
        height: 2px;
        background: #566276;
        border-radius: 3px;
    }


    .page-title h1 {
        margin: 0;
        color: #182238;
        font-size: 27px;
        font-weight: 800;
        letter-spacing: -.7px;
    }


    .page-title p {
        margin: 5px 0 0;
        color: #8994a5;
        font-size: 12px;
    }


    .new-sale {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 16px;
        border-radius: 11px;

        background:
            linear-gradient(
                135deg,
                #2563eb,
                #4f46e5
            );

        color: #fff;
        text-decoration: none;
        font-size: 11px;
        font-weight: 700;

        box-shadow:
            0 8px 20px rgba(50,90,210,.20);

        transition:
            transform .2s ease,
            box-shadow .2s ease;
    }


    .new-sale:hover {
        transform: translateY(-2px);

        box-shadow:
            0 12px 25px rgba(50,90,210,.28);
    }


    .new-sale svg {
        width: 16px;
        height: 16px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
    }


    /* =========================================================
       STATISTICS
    ========================================================= */

    .stats-grid {
        display: grid;

        grid-template-columns:
            repeat(5, minmax(0, 1fr));

        gap: 14px;

        margin-bottom: 20px;
    }


    .stat-card {
        background: rgba(255,255,255,.95);

        border: 1px solid #e4e9f0;

        border-radius: 15px;

        padding: 17px;

        box-shadow:
            0 9px 28px rgba(35,55,80,.055);

        animation:
            cardEnter .5s ease both;

        transition:
            transform .22s ease,
            box-shadow .22s ease;
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


    .stat-card:hover {
        transform: translateY(-4px);

        box-shadow:
            0 15px 30px rgba(35,55,80,.09);
    }


    @keyframes cardEnter {

        from {
            opacity: 0;
            transform: translateY(12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }

    }


    .stat-label {
        color: #8b96a7;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .7px;
    }


    .stat-value {
        margin-top: 8px;
        color: #202d44;
        font-size: 21px;
        font-weight: 800;
        white-space: nowrap;
    }


    .stat-description {
        margin-top: 4px;
        color: #a0a8b5;
        font-size: 9px;
    }


    .today-value {
        color: #2767c7;
    }


    /* =========================================================
       SUCCESS
    ========================================================= */

    .success {
        display: flex;
        align-items: center;
        gap: 9px;

        padding: 12px 15px;

        margin-bottom: 18px;

        background: #ecfdf3;

        border: 1px solid #ccefdc;

        border-radius: 11px;

        color: #16794a;

        font-size: 10px;
        font-weight: 700;

        animation:
            successEnter .45s ease both;
    }


    .success svg {
        width: 16px;
        height: 16px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }


    @keyframes successEnter {

        from {
            opacity: 0;
            transform: translateY(-7px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }

    }


    /* =========================================================
       SALES PANEL
    ========================================================= */

    .sales-panel {
        background: rgba(255,255,255,.95);

        border: 1px solid #e4e9f0;

        border-radius: 17px;

        overflow: hidden;

        box-shadow:
            0 10px 32px rgba(35,55,80,.06);

        animation:
            panelEnter .6s ease both;
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
        justify-content: space-between;
        align-items: center;

        padding: 18px 20px;

        border-bottom: 1px solid #edf1f5;
    }


    .panel-title strong {
        display: block;
        color: #27344a;
        font-size: 13px;
    }


    .panel-title span {
        display: block;
        margin-top: 4px;
        color: #9aa4b3;
        font-size: 10px;
    }


    /* =========================================================
       TABLE
    ========================================================= */

    .table-wrapper {
        overflow-x: auto;
    }


    table {
        width: 100%;
        min-width: 950px;
        border-collapse: collapse;
    }


    th {
        padding: 12px 18px;

        background: #fafbfd;

        color: #98a2b1;

        text-align: left;

        font-size: 9px;

        font-weight: 800;

        text-transform: uppercase;

        letter-spacing: .55px;

        border-bottom:
            1px solid #e9edf2;

        white-space: nowrap;
    }


    td {
        padding: 13px 18px;

        border-bottom:
            1px solid #eef1f5;

        color: #647085;

        font-size: 10px;

        vertical-align: middle;
    }


    tbody tr {
        transition:
            background .18s ease;
    }


    tbody tr:hover {
        background: #fafcff;
    }


    tbody tr:last-child td {
        border-bottom: none;
    }


    /* =========================================================
       SALE ID
    ========================================================= */

    .sale-id {
        display: inline-flex;

        align-items: center;

        justify-content: center;

        min-width: 34px;
        height: 27px;

        padding: 0 8px;

        border-radius: 8px;

        background: #f1f4f8;

        color: #526075;

        font-size: 9px;

        font-weight: 800;
    }


    /* =========================================================
       CUSTOMER
    ========================================================= */

    .customer-cell {
        display: flex;
        align-items: center;
        gap: 9px;
    }


    .customer-avatar {
        width: 32px;
        height: 32px;

        min-width: 32px;

        border-radius: 9px;

        display: flex;

        align-items: center;
        justify-content: center;

        background:
            linear-gradient(
                135deg,
                #eaf1ff,
                #f0edff
            );

        color: #4568a9;

        font-size: 10px;
        font-weight: 800;
    }


    .customer-name {
        color: #334159;
        font-size: 10px;
        font-weight: 700;
    }


    .customer-type {
        margin-top: 3px;
        color: #a0a8b5;
        font-size: 8px;
    }


    /* =========================================================
       USER
    ========================================================= */

    .recorded-by {
        color: #68758a;
        font-size: 9px;
    }


    /* =========================================================
       ITEMS
    ========================================================= */

    .items-badge {
        display: inline-flex;

        align-items: center;

        justify-content: center;

        min-width: 29px;

        padding: 5px 8px;

        border-radius: 7px;

        background: #eef6ff;

        color: #2862b9;

        font-size: 8px;

        font-weight: 800;
    }


    /* =========================================================
       AMOUNT
    ========================================================= */

    .amount {
        color: #293750;
        font-weight: 800;
        white-space: nowrap;
    }


    /* =========================================================
       DATE
    ========================================================= */

    .date-cell {
        color: #738096;
        white-space: nowrap;
    }


    /* =========================================================
       VIEW BUTTON
    ========================================================= */

    .view-button {
        display: inline-flex;

        align-items: center;

        gap: 5px;

        padding: 7px 10px;

        border-radius: 8px;

        background: #f2f5f9;

        color: #42516a;

        text-decoration: none;

        font-size: 8px;

        font-weight: 800;

        transition:
            background .18s ease,
            color .18s ease,
            transform .18s ease;
    }


    .view-button:hover {
        background: #e8eef8;
        color: #2559a6;
        transform: translateY(-1px);
    }


    .view-button svg {
        width: 12px;
        height: 12px;

        fill: none;

        stroke: currentColor;

        stroke-width: 1.8;

        stroke-linecap: round;

        stroke-linejoin: round;
    }


    /* =========================================================
       EMPTY STATE
    ========================================================= */

    .empty {
        text-align: center;
        padding: 65px 20px;
    }


    .empty-icon {
        width: 55px;
        height: 55px;

        margin:
            0 auto 13px;

        border-radius: 15px;

        display: flex;

        align-items: center;
        justify-content: center;

        background: #f1f4f8;

        color: #8b96a6;
    }


    .empty-icon svg {
        width: 25px;
        height: 25px;

        fill: none;

        stroke: currentColor;

        stroke-width: 1.5;

        stroke-linecap: round;

        stroke-linejoin: round;
    }


    .empty strong {
        display: block;

        color: #4c596d;

        font-size: 12px;
    }


    .empty span {
        display: block;

        margin-top: 5px;

        color: #a0a8b5;

        font-size: 10px;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================= */

    @media (max-width: 1200px) {

        .stats-grid {
            grid-template-columns:
                repeat(3, 1fr);
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


        .stats-grid {
            grid-template-columns:
                repeat(2, 1fr);
        }

    }


    @media (max-width: 600px) {

        .main-content {
            padding: 15px;
        }


        .page-header {
            align-items: flex-start;
        }


        .page-title h1 {
            font-size: 22px;
        }


        .page-title p {
            font-size: 10px;
        }


        .new-sale {
            padding: 10px;
            font-size: 0;
        }


        .new-sale svg {
            width: 18px;
            height: 18px;
        }


        .stats-grid {
            grid-template-columns:
                1fr 1fr;

            gap: 10px;
        }


        .stat-card {
            padding: 14px;
        }


        .stat-value {
            font-size: 18px;
        }


        .panel-header {
            padding: 15px;
        }

    }


    @media (max-width: 400px) {

        .stats-grid {
            grid-template-columns:
                1fr;
        }

    }

</style>

</head>

<body>

<div class="ambient">

<div class="ambient-one"></div>

<div class="ambient-two"></div>


</div>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="main-content">

<div class="page-wrapper">


    <!-- HEADER -->

    <header class="page-header">


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


            <div class="page-title">

                <h1>
                    Sales
                </h1>

                <p>
                    Record, monitor and manage customer sales.
                </p>

            </div>


        </div>


        <a
            href="add.php"
            class="new-sale"
        >

            <svg viewBox="0 0 24 24">

                <path d="M12 5v14"/>

                <path d="M5 12h14"/>

            </svg>

            <span>
                New Sale
            </span>

        </a>


    </header>


    <!-- STATISTICS -->

    <section class="stats-grid">


        <div class="stat-card">

            <div class="stat-label">
                Total Sales
            </div>

            <div
                class="stat-value counter"
                data-value="<?= $totalSales ?>"
            >
                0
            </div>

            <div class="stat-description">
                All recorded transactions
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Total Revenue
            </div>

            <div class="stat-value">

                KSh

                <span
                    class="counter"
                    data-value="<?= $totalRevenue ?>"
                    data-decimal="2"
                >
                    0
                </span>

            </div>

            <div class="stat-description">
                Revenue from all sales
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Today's Sales
            </div>

            <div
                class="stat-value today-value counter"
                data-value="<?= $todaySales ?>"
            >
                0
            </div>

            <div class="stat-description">
                Transactions today
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Today's Revenue
            </div>

            <div class="stat-value today-value">

                KSh

                <span
                    class="counter"
                    data-value="<?= $todayRevenue ?>"
                    data-decimal="2"
                >
                    0
                </span>

            </div>

            <div class="stat-description">
                Revenue generated today
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Average Sale
            </div>

            <div class="stat-value">

                KSh

                <span
                    class="counter"
                    data-value="<?= $averageSale ?>"
                    data-decimal="2"
                >
                    0
                </span>

            </div>

            <div class="stat-description">
                Average transaction value
            </div>

        </div>


    </section>


    <!-- SUCCESS MESSAGE -->

    <?php if (isset($_GET['success'])): ?>

        <div class="success">

            <svg viewBox="0 0 24 24">

                <path d="M20 6 9 17l-5-5"/>

            </svg>

            Sale recorded successfully.

        </div>

    <?php endif; ?>


    <!-- SALES TABLE -->

    <section class="sales-panel">


        <div class="panel-header">


            <div class="panel-title">

                <strong>
                    Sales History
                </strong>

                <span>
                    <?= count($sales) ?>
                    recorded sale<?= count($sales) === 1 ? '' : 's' ?>
                </span>

            </div>


        </div>


        <div class="table-wrapper">


            <?php if (empty($sales)): ?>


                <div class="empty">


                    <div class="empty-icon">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M6 2h9l4 4v16H6z"
                            />

                            <path
                                d="M14 2v5h5"
                            />

                            <path
                                d="M9 13h6"
                            />

                            <path
                                d="M9 17h4"
                            />

                        </svg>

                    </div>


                    <strong>
                        No sales recorded yet
                    </strong>


                    <span>
                        Start by creating your first sale.
                    </span>


                </div>


            <?php else: ?>


                <table>


                    <thead>

                        <tr>

                            <th>
                                Sale
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Recorded By
                            </th>

                            <th>
                                Items
                            </th>

                            <th>
                                Total Amount
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($sales as $sale): ?>


                            <?php

                            $customerName =
                                $sale['customer_name']
                                ?? 'Walk-in Customer';

                            $initial =
                                strtoupper(
                                    substr(
                                        $customerName,
                                        0,
                                        1
                                    )
                                );

                            ?>


                            <tr>


                                <!-- SALE -->

                                <td>

                                    <span class="sale-id">

                                        #
                                        <?= (int) $sale['id'] ?>

                                    </span>

                                </td>


                                <!-- CUSTOMER -->

                                <td>

                                    <div class="customer-cell">


                                        <div class="customer-avatar">

                                            <?= htmlspecialchars(
                                                $initial
                                            ) ?>

                                        </div>


                                        <div>

                                            <div class="customer-name">

                                                <?= htmlspecialchars(
                                                    $customerName
                                                ) ?>

                                            </div>


                                            <div class="customer-type">

                                                <?php if (
                                                    empty(
                                                        $sale['customer_name']
                                                    )
                                                ): ?>

                                                    Walk-in

                                                <?php else: ?>

                                                    Registered customer

                                                <?php endif; ?>

                                            </div>

                                        </div>


                                    </div>

                                </td>


                                <!-- RECORDED BY -->

                                <td>

                                    <span class="recorded-by">

                                        <?= htmlspecialchars(
                                            $sale['user_name']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ITEMS -->

                                <td>

                                    <span class="items-badge">

                                        <?= (int) $sale['item_count'] ?>

                                        item<?= (int) $sale['item_count'] === 1 ? '' : 's' ?>

                                    </span>

                                </td>


                                <!-- TOTAL -->

                                <td class="amount">

                                    KSh
                                    <?= number_format(
                                        (float) $sale['total_amount'],
                                        2
                                    ) ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <span class="date-cell">

                                        <?= htmlspecialchars(
                                            $sale['sale_date']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="view.php?id=<?= (int) $sale['id'] ?>"
                                        class="view-button"
                                    >

                                        <svg viewBox="0 0 24 24">

                                            <path
                                                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"
                                            />

                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="2.5"
                                            />

                                        </svg>

                                        View

                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            <?php endif; ?>


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


        document
            .querySelectorAll('.counter')
            .forEach(
                function (counter) {


                    const target =
                        parseFloat(
                            counter.dataset.value || 0
                        );


                    const decimals =
                        parseInt(
                            counter.dataset.decimal || 0
                        );


                    const duration =
                        900;


                    const start =
                        performance.now();


                    function animate(time) {


                        const progress =
                            Math.min(
                                (time - start) /
                                duration,
                                1
                            );


                        const eased =
                            1 -
                            Math.pow(
                                1 - progress,
                                3
                            );


                        const value =
                            target * eased;


                        counter.textContent =
                            value.toLocaleString(
                                'en-KE',
                                {
                                    minimumFractionDigits:
                                        decimals,

                                    maximumFractionDigits:
                                        decimals
                                }
                            );


                        if (progress < 1) {

                            requestAnimationFrame(
                                animate
                            );

                        }

                    }


                    requestAnimationFrame(
                        animate
                    );

                }
            );


        /*
        |--------------------------------------------------------------------------
        | Mobile sidebar
        |--------------------------------------------------------------------------
        */

        const menu =
            document.getElementById(
                'mobileMenu'
            );


        if (menu) {

            menu.addEventListener(
                'click',
                function () {


                    const sidebar =
                        document.getElementById(
                            'sidebar'
                        );


                    const overlay =
                        document.getElementById(
                            'sidebarOverlay'
                        );


                    if (sidebar) {

                        sidebar.classList.add(
                            'open'
                        );

                    }


                    if (overlay) {

                        overlay.classList.add(
                            'show'
                        );

                    }

                }
            );

        }


    }
);

</script>

</body>

</html>
