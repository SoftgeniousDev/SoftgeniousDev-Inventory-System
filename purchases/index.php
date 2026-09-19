<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$purchases = [];

$totalPurchases = 0;
$totalSpent = 0;
$todayPurchases = 0;
$todaySpent = 0;
$averagePurchase = 0;

try {

    /*
    |--------------------------------------------------------------------------
    | Purchase History
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            purchases.id,
            suppliers.name AS supplier_name,
            users.name AS user_name,
            purchases.total_amount,
            purchases.purchase_date,
            COUNT(purchase_items.id) AS item_count
        FROM purchases

        INNER JOIN suppliers
            ON purchases.supplier_id = suppliers.id

        INNER JOIN users
            ON purchases.user_id = users.id

        LEFT JOIN purchase_items
            ON purchases.id = purchase_items.purchase_id

        GROUP BY
            purchases.id,
            suppliers.name,
            users.name,
            purchases.total_amount,
            purchases.purchase_date

        ORDER BY purchases.id DESC
    ");

    $purchases = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Purchase Statistics
    |--------------------------------------------------------------------------
    */

    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_purchases,
            COALESCE(SUM(total_amount), 0) AS total_spent,
            COALESCE(AVG(total_amount), 0) AS average_purchase,

            COALESCE(
                SUM(
                    CASE
                        WHEN DATE(purchase_date) = CURDATE()
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS today_purchases,

            COALESCE(
                SUM(
                    CASE
                        WHEN DATE(purchase_date) = CURDATE()
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS today_spent

        FROM purchases
    ");

    $stats = $statsStmt->fetch();

    if ($stats) {

        $totalPurchases = (int) ($stats['total_purchases'] ?? 0);

        $totalSpent = (float) ($stats['total_spent'] ?? 0);

        $todayPurchases = (int) ($stats['today_purchases'] ?? 0);

        $todaySpent = (float) ($stats['today_spent'] ?? 0);

        $averagePurchase = (float) ($stats['average_purchase'] ?? 0);
    }

} catch (Throwable $e) {

    if (function_exists('logError')) {

        logError(
            'Failed to load purchases page.',
            $e
        );
    }

    $purchases = [];

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

<title>Purchases | SoftgeniousDev</title>

<style>

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family:
            Arial,
            Helvetica,
            sans-serif;

        background:
            linear-gradient(
                135deg,
                #f5f8fc 0%,
                #eef3f8 100%
            );

        color: #172033;
    }

    .main {
        margin-left: 260px;
        min-height: 100vh;
        padding: 35px;
        position: relative;
        overflow: hidden;
    }

    /*
    |--------------------------------------------------------------------------
    | Ambient Background
    |--------------------------------------------------------------------------
    */

    .ambient {
        position: fixed;
        width: 420px;
        height: 420px;
        border-radius: 50%;
        filter: blur(90px);
        opacity: 0.18;
        pointer-events: none;
        animation: float 10s ease-in-out infinite;
    }

    .ambient-one {
        background: #1570ef;
        top: -180px;
        right: -100px;
    }

    .ambient-two {
        background: #7f56d9;
        bottom: -220px;
        left: 220px;
        animation-delay: -4s;
    }

    @keyframes float {

        0%,
        100% {
            transform: translate(
                0,
                0
            );
        }

        50% {
            transform: translate(
                20px,
                -20px
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 28px;
        position: relative;
        z-index: 1;
    }

    .page-header h1 {
        margin: 0 0 7px;
        font-size: 30px;
        letter-spacing: -0.6px;
    }

    .page-header p {
        margin: 0;
        color: #667085;
        font-size: 14px;
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;

        padding: 12px 18px;

        border-radius: 10px;

        background: #1570ef;
        color: #ffffff;

        text-decoration: none;
        font-size: 14px;
        font-weight: 700;

        box-shadow:
            0 8px 18px
            rgba(21, 112, 239, 0.20);

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }

    .btn-primary:hover {
        background: #175cd3;

        transform: translateY(-2px);

        box-shadow:
            0 12px 25px
            rgba(21, 112, 239, 0.28);
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    .stats-grid {
        display: grid;

        grid-template-columns:
            repeat(5, minmax(0, 1fr));

        gap: 16px;

        margin-bottom: 26px;

        position: relative;
        z-index: 1;
    }

    .stat-card {
        background: rgba(
            255,
            255,
            255,
            0.94
        );

        border: 1px solid #e4e7ec;

        border-radius: 16px;

        padding: 20px;

        box-shadow:
            0 8px 25px
            rgba(16, 24, 40, 0.055);

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);

        box-shadow:
            0 14px 32px
            rgba(16, 24, 40, 0.10);
    }

    .stat-label {
        font-size: 12px;
        font-weight: 700;
        color: #667085;

        text-transform: uppercase;

        letter-spacing: 0.5px;

        margin-bottom: 9px;
    }

    .stat-value {
        font-size: 25px;
        font-weight: 800;

        color: #101828;

        letter-spacing: -0.5px;
    }

    .stat-subtitle {
        margin-top: 7px;

        font-size: 12px;

        color: #98a2b3;
    }

    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    .success {
        background: #ecfdf3;
        border: 1px solid #abefc6;

        color: #027a48;

        padding: 13px 16px;

        border-radius: 10px;

        margin-bottom: 20px;

        font-size: 14px;
        font-weight: 600;

        position: relative;
        z-index: 2;
    }

    /*
    |--------------------------------------------------------------------------
    | Main Card
    |--------------------------------------------------------------------------
    */

    .card {
        background: rgba(
            255,
            255,
            255,
            0.96
        );

        border: 1px solid #e4e7ec;

        border-radius: 16px;

        overflow: hidden;

        box-shadow:
            0 8px 28px
            rgba(16, 24, 40, 0.06);

        position: relative;
        z-index: 1;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;

        padding: 20px 22px;

        border-bottom: 1px solid #eaecf0;
    }

    .card-title {
        margin: 0;

        font-size: 17px;
        font-weight: 750;
    }

    .card-description {
        margin: 4px 0 0;

        font-size: 13px;

        color: #667085;
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    .table-wrapper {
        overflow-x: auto;
    }

    table {
        width: 100%;

        min-width: 900px;

        border-collapse: collapse;
    }

    th,
    td {
        padding: 16px 20px;

        text-align: left;

        border-bottom:
            1px solid #eaecf0;
    }

    th {
        background: #f9fafb;

        color: #475467;

        font-size: 11px;

        text-transform: uppercase;

        letter-spacing: 0.5px;

        font-weight: 750;
    }

    td {
        font-size: 14px;

        color: #344054;
    }

    tbody tr {
        transition:
            background 0.18s ease;
    }

    tbody tr:hover {
        background: #f8fafc;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    .purchase-id {
        font-weight: 750;
        color: #1570ef;
    }

    .supplier-name {
        font-weight: 700;
        color: #101828;
    }

    .user-name {
        color: #475467;
    }

    .amount {
        font-weight: 750;
        color: #101828;
        white-space: nowrap;
    }

    .items-badge {
        display: inline-flex;

        align-items: center;
        justify-content: center;

        min-width: 34px;

        padding: 5px 9px;

        border-radius: 20px;

        background: #eff8ff;

        color: #175cd3;

        font-size: 12px;

        font-weight: 750;
    }

    .date {
        color: #667085;
        white-space: nowrap;
    }

    .btn-view {
        display: inline-flex;

        align-items: center;
        justify-content: center;

        padding: 8px 13px;

        border-radius: 8px;

        background: #f2f4f7;

        color: #344054;

        text-decoration: none;

        font-size: 12px;

        font-weight: 700;

        transition:
            background 0.2s ease,
            transform 0.2s ease;
    }

    .btn-view:hover {
        background: #e4e7ec;

        transform: translateY(-1px);
    }

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    .empty {
        padding: 65px 25px;

        text-align: center;

        color: #667085;

        font-size: 14px;
    }

    .empty-icon {
        width: 52px;
        height: 52px;

        margin: 0 auto 15px;

        border-radius: 14px;

        background: #f2f4f7;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 22px;

        color: #667085;
    }

    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1250px) {

        .stats-grid {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
        }

    }

    @media (max-width: 900px) {

        .main {
            margin-left: 0;
            padding: 25px 18px;
        }

        .stats-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }

    @media (max-width: 600px) {

        .main {
            padding: 20px 14px;
        }

        .page-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .page-header h1 {
            font-size: 25px;
        }

        .btn-primary {
            width: 100%;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-card {
            padding: 17px;
        }

        .card-header {
            padding: 17px;
        }

    }

</style>

</head>

<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="ambient ambient-one"></div>
<div class="ambient ambient-two"></div>

<main class="main">

<div class="page-header">

    <div>

        <h1>Purchases</h1>

        <p>
            Record and manage stock purchases from suppliers.
        </p>

    </div>

    <a
        href="add.php"
        class="btn-primary"
    >
        + New Purchase
    </a>

</div>


<?php if (isset($_GET['success'])): ?>

    <div class="success">
        Purchase recorded successfully.
    </div>

<?php endif; ?>


<!-- Statistics -->

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-label">
            Total Purchases
        </div>

        <div
            class="stat-value counter"
            data-value="<?= $totalPurchases ?>"
        >
            0
        </div>

        <div class="stat-subtitle">
            All recorded purchases
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Total Spent
        </div>

        <div class="stat-value">

            KSh
            <span
                class="money-counter"
                data-value="<?= $totalSpent ?>"
            >
                0.00
            </span>

        </div>

        <div class="stat-subtitle">
            Total purchase value
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Today's Purchases
        </div>

        <div
            class="stat-value counter"
            data-value="<?= $todayPurchases ?>"
        >
            0
        </div>

        <div class="stat-subtitle">
            Purchases recorded today
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Today's Spending
        </div>

        <div class="stat-value">

            KSh
            <span
                class="money-counter"
                data-value="<?= $todaySpent ?>"
            >
                0.00
            </span>

        </div>

        <div class="stat-subtitle">
            Amount spent today
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Average Purchase
        </div>

        <div class="stat-value">

            KSh
            <span
                class="money-counter"
                data-value="<?= $averagePurchase ?>"
            >
                0.00
            </span>

        </div>

        <div class="stat-subtitle">
            Average value per purchase
        </div>

    </div>

</div>


<!-- Purchase History -->

<div class="card">

    <div class="card-header">

        <div>

            <h2 class="card-title">
                Purchase History
            </h2>

            <p class="card-description">
                View previously recorded supplier purchases.
            </p>

        </div>

    </div>


    <div class="table-wrapper">

        <?php if (empty($purchases)): ?>

            <div class="empty">

                <div class="empty-icon">
                    +
                </div>

                No purchases have been recorded yet.

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Supplier
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

                    <?php foreach ($purchases as $purchase): ?>

                        <tr>

                            <td class="purchase-id">

                                #<?= (int) $purchase['id'] ?>

                            </td>


                            <td class="supplier-name">

                                <?= htmlspecialchars(
                                    $purchase['supplier_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td class="user-name">

                                <?= htmlspecialchars(
                                    $purchase['user_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <span class="items-badge">

                                    <?= (int) $purchase['item_count'] ?>

                                </span>

                            </td>


                            <td class="amount">

                                KSh
                                <?= number_format(
                                    (float) $purchase['total_amount'],
                                    2
                                ) ?>

                            </td>


                            <td class="date">

                                <?= htmlspecialchars(
                                    $purchase['purchase_date'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <a
                                    href="view.php?id=<?= (int) $purchase['id'] ?>"
                                    class="btn-view"
                                >
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</main>

<script>

    /*
    |--------------------------------------------------------------------------
    | Animated Number Counters
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.counter')
        .forEach(function (element) {

            const target =
                Number(
                    element.dataset.value
                ) || 0;

            const duration = 700;

            const startTime = performance.now();

            function animate(currentTime) {

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

                element.textContent =
                    Math.floor(
                        target * eased
                    ).toLocaleString();

                if (progress < 1) {

                    requestAnimationFrame(
                        animate
                    );

                } else {

                    element.textContent =
                        target.toLocaleString();

                }

            }

            requestAnimationFrame(
                animate
            );

        });


    document
        .querySelectorAll('.money-counter')
        .forEach(function (element) {

            const target =
                Number(
                    element.dataset.value
                ) || 0;

            const duration = 800;

            const startTime = performance.now();

            function animate(currentTime) {

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

                const value =
                    target * eased;

                element.textContent =
                    value.toLocaleString(
                        'en-KE',
                        {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    );

                if (progress < 1) {

                    requestAnimationFrame(
                        animate
                    );

                } else {

                    element.textContent =
                        target.toLocaleString(
                            'en-KE',
                            {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }
                        );

                }

            }

            requestAnimationFrame(
                animate
            );

        });

</script>

</body>

</html>
