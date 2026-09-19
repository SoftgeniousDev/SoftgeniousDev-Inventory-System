<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$customers = [];

$totalCustomers = 0;
$customersWithSales = 0;
$totalCustomerSales = 0;
$averageCustomerSpend = 0;

try {

    /*
    |--------------------------------------------------------------------------
    | Customer List
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            customers.id,
            customers.name,
            customers.phone,
            customers.email,
            customers.address,

            COUNT(sales.id) AS sale_count,

            COALESCE(
                SUM(sales.total_amount),
                0
            ) AS total_spent

        FROM customers

        LEFT JOIN sales
            ON customers.id = sales.customer_id

        GROUP BY
            customers.id,
            customers.name,
            customers.phone,
            customers.email,
            customers.address

        ORDER BY customers.id DESC
    ");

    $customers = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Customer Statistics
    |--------------------------------------------------------------------------
    */

    $statsStmt = $pdo->query("
        SELECT

            COUNT(DISTINCT customers.id)
                AS total_customers,

            COUNT(
                DISTINCT
                CASE
                    WHEN sales.id IS NOT NULL
                    THEN customers.id
                END
            ) AS customers_with_sales,

            COALESCE(
                SUM(sales.total_amount),
                0
            ) AS total_customer_sales

        FROM customers

        LEFT JOIN sales
            ON customers.id = sales.customer_id
    ");

    $stats = $statsStmt->fetch();

    if ($stats) {

        $totalCustomers =
            (int) ($stats['total_customers'] ?? 0);

        $customersWithSales =
            (int) ($stats['customers_with_sales'] ?? 0);

        $totalCustomerSales =
            (float) ($stats['total_customer_sales'] ?? 0);

        if ($customersWithSales > 0) {

            $averageCustomerSpend =
                $totalCustomerSales /
                $customersWithSales;

        }

    }

} catch (Throwable $e) {

    if (function_exists('logError')) {

        logError(
            'Failed to load customers page.',
            $e
        );

    }

    $customers = [];

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

<title>Customers | SoftgeniousDev</title>

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


    /*
    |--------------------------------------------------------------------------
    | Main Content
    |--------------------------------------------------------------------------
    */

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

        animation:
            float 10s
            ease-in-out
            infinite;
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
            transform:
                translate(
                    0,
                    0
                );
        }

        50% {
            transform:
                translate(
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

        justify-content:
            space-between;

        align-items: center;

        gap: 20px;

        margin-bottom: 28px;

        position: relative;

        z-index: 1;
    }

    .page-header h1 {

        margin:
            0 0 7px;

        font-size: 30px;

        letter-spacing:
            -0.6px;
    }

    .page-header p {

        margin: 0;

        color: #667085;

        font-size: 14px;
    }


    /*
    |--------------------------------------------------------------------------
    | Primary Button
    |--------------------------------------------------------------------------
    */

    .btn-primary {

        display: inline-flex;

        align-items: center;

        justify-content: center;

        gap: 8px;

        padding:
            12px 18px;

        border-radius: 10px;

        background: #1570ef;

        color: #ffffff;

        text-decoration: none;

        font-size: 14px;

        font-weight: 700;

        box-shadow:
            0 8px 18px
            rgba(
                21,
                112,
                239,
                0.20
            );

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }

    .btn-primary:hover {

        background: #175cd3;

        transform:
            translateY(-2px);

        box-shadow:
            0 12px 25px
            rgba(
                21,
                112,
                239,
                0.28
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    .stats-grid {

        display: grid;

        grid-template-columns:
            repeat(
                4,
                minmax(0, 1fr)
            );

        gap: 16px;

        margin-bottom: 26px;

        position: relative;

        z-index: 1;
    }

    .stat-card {

        background:
            rgba(
                255,
                255,
                255,
                0.94
            );

        border:
            1px solid #e4e7ec;

        border-radius: 16px;

        padding: 20px;

        box-shadow:
            0 8px 25px
            rgba(
                16,
                24,
                40,
                0.055
            );

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .stat-card:hover {

        transform:
            translateY(-4px);

        box-shadow:
            0 14px 32px
            rgba(
                16,
                24,
                40,
                0.10
            );
    }

    .stat-label {

        font-size: 12px;

        font-weight: 700;

        color: #667085;

        text-transform:
            uppercase;

        letter-spacing:
            0.5px;

        margin-bottom: 9px;
    }

    .stat-value {

        font-size: 25px;

        font-weight: 800;

        color: #101828;

        letter-spacing:
            -0.5px;
    }

    .stat-subtitle {

        margin-top: 7px;

        font-size: 12px;

        color: #98a2b3;
    }


    /*
    |--------------------------------------------------------------------------
    | Message
    |--------------------------------------------------------------------------
    */

    .message {

        margin-bottom: 20px;

        padding:
            13px 16px;

        border-radius: 10px;

        background: #ecfdf3;

        color: #027a48;

        border:
            1px solid #abefc6;

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

        background:
            rgba(
                255,
                255,
                255,
                0.96
            );

        border:
            1px solid #e4e7ec;

        border-radius: 16px;

        overflow: hidden;

        box-shadow:
            0 8px 28px
            rgba(
                16,
                24,
                40,
                0.06
            );

        position: relative;

        z-index: 1;
    }

    .card-header {

        display: flex;

        justify-content:
            space-between;

        align-items: center;

        padding:
            20px 22px;

        border-bottom:
            1px solid #eaecf0;
    }

    .card-title {

        margin: 0;

        font-size: 17px;

        font-weight: 750;
    }

    .card-description {

        margin:
            4px 0 0;

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

        min-width: 1050px;

        border-collapse:
            collapse;
    }

    th,
    td {

        padding:
            16px 20px;

        text-align: left;

        border-bottom:
            1px solid #eaecf0;
    }

    th {

        background: #f9fafb;

        color: #475467;

        font-size: 11px;

        text-transform:
            uppercase;

        letter-spacing:
            0.5px;

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


    /*
    |--------------------------------------------------------------------------
    | Customer Information
    |--------------------------------------------------------------------------
    */

    .customer-id {

        font-weight: 750;

        color: #1570ef;
    }

    .customer-name {

        font-weight: 700;

        color: #101828;
    }

    .contact {

        color: #475467;

        white-space: nowrap;
    }

    .address {

        color: #667085;

        max-width: 220px;

        overflow: hidden;

        text-overflow: ellipsis;

        white-space: nowrap;
    }


    /*
    |--------------------------------------------------------------------------
    | Sales Badge
    |--------------------------------------------------------------------------
    */

    .badge {

        display: inline-flex;

        align-items: center;

        justify-content: center;

        min-width: 34px;

        padding:
            5px 9px;

        border-radius: 20px;

        background: #eff8ff;

        color: #175cd3;

        font-size: 12px;

        font-weight: 750;
    }


    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */

    .amount {

        font-weight: 750;

        color: #101828;

        white-space: nowrap;
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Button
    |--------------------------------------------------------------------------
    */

    .btn-danger {

        border: none;

        padding:
            8px 13px;

        border-radius: 8px;

        background: #fef3f2;

        color: #b42318;

        font-size: 12px;

        font-weight: 700;

        cursor: pointer;

        transition:
            background 0.2s ease,
            transform 0.2s ease;
    }

    .btn-danger:hover {

        background: #fee4e2;

        transform:
            translateY(-1px);
    }

    .actions {

        display: flex;

        align-items: center;

        gap: 8px;
    }

    .delete-form {

        margin: 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    .empty {

        padding:
            65px 25px;

        text-align: center;

        color: #667085;

        font-size: 14px;
    }

    .empty-icon {

        width: 52px;

        height: 52px;

        margin:
            0 auto 15px;

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

    @media (max-width: 1100px) {

        .stats-grid {

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );
        }

    }

    @media (max-width: 900px) {

        .main {

            margin-left: 0;

            padding:
                25px 18px;
        }

    }

    @media (max-width: 600px) {

        .main {

            padding:
                20px 14px;
        }

        .page-header {

            align-items:
                flex-start;

            flex-direction:
                column;
        }

        .page-header h1 {

            font-size: 25px;
        }

        .btn-primary {

            width: 100%;
        }

        .stats-grid {

            grid-template-columns:
                1fr;
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

        <h1>
            Customers
        </h1>

        <p>
            Manage customers and track their sales activity.
        </p>

    </div>

    <a
        href="add.php"
        class="btn-primary"
    >
        + Add Customer
    </a>

</div>


<?php if (isset($_GET['deleted'])): ?>

    <div class="message">
        Customer deleted successfully.
    </div>

<?php endif; ?>


<!-- Statistics -->

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-label">
            Total Customers
        </div>

        <div
            class="stat-value counter"
            data-value="<?= $totalCustomers ?>"
        >
            0
        </div>

        <div class="stat-subtitle">
            Registered customers
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Active Customers
        </div>

        <div
            class="stat-value counter"
            data-value="<?= $customersWithSales ?>"
        >
            0
        </div>

        <div class="stat-subtitle">
            Customers with purchases
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Customer Sales
        </div>

        <div class="stat-value">

            KSh

            <span
                class="money-counter"
                data-value="<?= $totalCustomerSales ?>"
            >
                0.00
            </span>

        </div>

        <div class="stat-subtitle">
            Total sales linked to customers
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Average Spend
        </div>

        <div class="stat-value">

            KSh

            <span
                class="money-counter"
                data-value="<?= $averageCustomerSpend ?>"
            >
                0.00
            </span>

        </div>

        <div class="stat-subtitle">
            Average per active customer
        </div>

    </div>

</div>


<!-- Customer Table -->

<div class="card">

    <div class="card-header">

        <div>

            <h2 class="card-title">
                Customer Directory
            </h2>

            <p class="card-description">
                View customer information and purchase activity.
            </p>

        </div>

    </div>


    <div class="table-wrapper">

        <?php if (empty($customers)): ?>

            <div class="empty">

                <div class="empty-icon">
                    +
                </div>

                No customers have been added yet.

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Address
                        </th>

                        <th>
                            Sales
                        </th>

                        <th>
                            Total Spent
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($customers as $customer): ?>

                        <tr>

                            <td class="customer-id">

                                #<?= (int) $customer['id'] ?>

                            </td>


                            <td class="customer-name">

                                <?= htmlspecialchars(
                                    $customer['name'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td class="contact">

                                <?= htmlspecialchars(
                                    $customer['phone'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td class="contact">

                                <?= htmlspecialchars(
                                    $customer['email'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td class="address">

                                <?= htmlspecialchars(
                                    $customer['address'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <span class="badge">

                                    <?= (int) $customer['sale_count'] ?>

                                </span>

                            </td>


                            <td class="amount">

                                KSh

                                <?= number_format(
                                    (float) $customer['total_spent'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <div class="actions">

                                    <form
                                        action="delete.php"
                                        method="POST"
                                        class="delete-form"
                                        onsubmit="return confirm('Are you sure you want to delete this customer?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $customer['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                csrf_token(),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-danger"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>

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
    | Animated Counters
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

            const startTime =
                performance.now();

            function animate(currentTime) {

                const elapsed =
                    currentTime -
                    startTime;

                const progress =
                    Math.min(
                        elapsed /
                        duration,
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
                        target *
                        eased
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


    /*
    |--------------------------------------------------------------------------
    | Money Counters
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.money-counter')
        .forEach(function (element) {

            const target =
                Number(
                    element.dataset.value
                ) || 0;

            const duration = 800;

            const startTime =
                performance.now();

            function animate(currentTime) {

                const elapsed =
                    currentTime -
                    startTime;

                const progress =
                    Math.min(
                        elapsed /
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
                    target *
                    eased;

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
