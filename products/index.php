<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$csrfToken = csrf_token();

$search = trim($_GET['search'] ?? '');
$lowStockOnly = isset($_GET['filter']) && $_GET['filter'] === 'low_stock';

try {

    $sql = "
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
            suppliers.name AS supplier_name
        FROM products
        LEFT JOIN suppliers
            ON products.supplier_id = suppliers.id
    ";

    $conditions = [];
    $params = [];

    if ($search !== '') {

        $conditions[] = "
            (
                products.name LIKE ?
                OR products.sku LIKE ?
                OR products.category LIKE ?
                OR suppliers.name LIKE ?
            )
        ";

        $term = '%' . $search . '%';

        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if ($lowStockOnly) {
        $conditions[] = "
            products.stock_quantity <= products.reorder_level
        ";
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= "
        ORDER BY products.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Product statistics
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(stock_quantity), 0) AS total_units,
            COALESCE(
                SUM(stock_quantity * buying_price),
                0
            ) AS inventory_value,
            COALESCE(
                SUM(
                    CASE
                        WHEN stock_quantity <= reorder_level
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS low_stock
        FROM products
    ");

    $stats = $stmt->fetch();

    $totalProducts = (int) ($stats['total_products'] ?? 0);
    $totalUnits = (int) ($stats['total_units'] ?? 0);
    $inventoryValue = (float) ($stats['inventory_value'] ?? 0);
    $lowStockCount = (int) ($stats['low_stock'] ?? 0);


} catch (Throwable $e) {

    logError(
        'Products page failed to load.',
        $e
    );

    $products = [];

    $totalProducts = 0;
    $totalUnits = 0;
    $inventoryValue = 0;
    $lowStockCount = 0;

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
    Products | Softgenious Inventory
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
       BACKGROUND
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
        opacity: .22;
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


    .add-button {
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


    .add-button:hover {
        transform: translateY(-2px);
        box-shadow:
            0 12px 25px rgba(50,90,210,.28);
    }


    .add-button svg {
        width: 16px;
        height: 16px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
    }


    /* =========================================================
       STAT CARDS
    ========================================================= */

    .stats-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 15px;
        margin-bottom: 20px;
    }


    .stat-card {
        padding: 17px;
        background: rgba(255,255,255,.94);
        border: 1px solid #e5eaf1;
        border-radius: 15px;

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
        font-size: 23px;
        font-weight: 800;
    }


    .stat-description {
        margin-top: 4px;
        color: #a0a8b5;
        font-size: 9px;
    }


    .warning-value {
        color: #d13e4d;
    }


    /* =========================================================
       PRODUCT PANEL
    ========================================================= */

    .product-panel {
        background: rgba(255,255,255,.95);
        border: 1px solid #e4e9f0;
        border-radius: 17px;
        box-shadow:
            0 10px 32px rgba(35,55,80,.06);

        overflow: hidden;

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


    .panel-toolbar {
        padding: 18px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        border-bottom: 1px solid #edf1f5;
    }


    .toolbar-title strong {
        display: block;
        color: #27344a;
        font-size: 13px;
    }


    .toolbar-title span {
        display: block;
        margin-top: 4px;
        color: #9aa4b3;
        font-size: 10px;
    }


    .search-form {
        display: flex;
        align-items: center;
        gap: 8px;
    }


    .search-box {
        position: relative;
    }


    .search-box svg {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        width: 15px;
        height: 15px;
        fill: none;
        stroke: #9aa4b3;
        stroke-width: 1.8;
        stroke-linecap: round;
    }


    .search-input {
        width: 270px;
        height: 39px;
        padding: 0 13px 0 34px;
        border: 1px solid #dfe5ec;
        border-radius: 10px;
        outline: none;
        background: #fafbfd;
        color: #37445a;
        font-size: 10px;
        transition:
            border-color .2s ease,
            box-shadow .2s ease,
            background .2s ease;
    }


    .search-input:focus {
        background: #fff;
        border-color: #8da9df;
        box-shadow:
            0 0 0 3px rgba(59,100,190,.08);
    }


    .search-button {
        height: 39px;
        padding: 0 13px;
        border: none;
        border-radius: 10px;
        background: #253f73;
        color: #fff;
        cursor: pointer;
        font-size: 10px;
        font-weight: 700;
    }


    .clear-button {
        height: 39px;
        padding: 0 12px;
        border-radius: 10px;
        border: 1px solid #dfe5ec;
        background: #fff;
        color: #697589;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        font-size: 10px;
        font-weight: 600;
    }


    .low-stock-filter {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 8px;
        padding: 8px 10px;
        border-radius: 8px;
        background: #fff2f2;
        color: #d13e4d;
        text-decoration: none;
        font-size: 9px;
        font-weight: 700;
    }


    .low-stock-filter.active {
        background: #d13e4d;
        color: #fff;
    }


    /* =========================================================
       TABLE
    ========================================================= */

    .table-wrap {
        overflow-x: auto;
    }


    .products-table {
        width: 100%;
        min-width: 900px;
        border-collapse: collapse;
    }


    .products-table th {
        padding: 12px 18px;
        background: #fafbfd;
        color: #98a2b1;
        text-align: left;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .55px;
        border-bottom: 1px solid #e9edf2;
        white-space: nowrap;
    }


    .products-table td {
        padding: 13px 18px;
        border-bottom: 1px solid #eef1f5;
        color: #647085;
        font-size: 10px;
        vertical-align: middle;
    }


    .products-table tbody tr {
        transition:
            background .18s ease,
            transform .18s ease;
    }


    .products-table tbody tr:hover {
        background: #fafcff;
    }


    .products-table tbody tr:last-child td {
        border-bottom: none;
    }


    /* =========================================================
       PRODUCT CELL
    ========================================================= */

    .product-cell {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 190px;
    }


    .product-image {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid #e5eaf0;
        background: #f3f6fa;
    }


    .product-placeholder {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            linear-gradient(
                135deg,
                #eef3ff,
                #f3efff
            );
        color: #5271bd;
        font-size: 13px;
        font-weight: 800;
    }


    .product-name {
        color: #334159;
        font-size: 11px;
        font-weight: 700;
    }


    .product-sku {
        margin-top: 4px;
        color: #9aa4b3;
        font-size: 8px;
    }


    .category {
        display: inline-block;
        padding: 5px 8px;
        border-radius: 7px;
        background: #f2f5f9;
        color: #697589;
        font-size: 8px;
        font-weight: 700;
    }


    .price {
        color: #3f4d63;
        font-weight: 700;
        white-space: nowrap;
    }


    .supplier {
        color: #68758a;
    }


    /* =========================================================
       STOCK
    ========================================================= */

    .stock-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 8px;
        border-radius: 7px;
        font-size: 8px;
        font-weight: 800;
        white-space: nowrap;
    }


    .stock-status.good {
        background: #edf9f2;
        color: #218a50;
    }


    .stock-status.low {
        background: #fff0f0;
        color: #d33e4d;
        animation:
            stockAlert 2.2s ease-in-out infinite;
    }


    .stock-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }


    @keyframes stockAlert {

        0%, 100% {
            box-shadow: 0 0 0 0 rgba(211,62,77,0);
        }

        50% {
            box-shadow: 0 0 0 4px rgba(211,62,77,.07);
        }

    }


    /* =========================================================
       ACTIONS
    ========================================================= */

    .actions {
        display: flex;
        align-items: center;
        gap: 6px;
    }


    .action-button {
        width: 31px;
        height: 31px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        text-decoration: none;
        border: 1px solid #e3e8ef;
        background: #fff;
        cursor: pointer;
        transition:
            transform .18s ease,
            background .18s ease,
            color .18s ease;
    }


    .action-button:hover {
        transform: translateY(-2px);
    }


    .action-button svg {
        width: 14px;
        height: 14px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }


    .edit-action {
        color: #3766be;
    }


    .edit-action:hover {
        background: #eef4ff;
    }


    .delete-action {
        color: #d13e4d;
    }


    .delete-action:hover {
        background: #fff0f0;
    }


    .delete-form {
        margin: 0;
    }


    /* =========================================================
       EMPTY STATE
    ========================================================= */

    .empty-state {
        padding: 65px 20px;
        text-align: center;
    }


    .empty-icon {
        width: 55px;
        height: 55px;
        margin: 0 auto 13px;
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


    .empty-state strong {
        display: block;
        color: #4c596d;
        font-size: 12px;
    }


    .empty-state span {
        display: block;
        margin-top: 5px;
        color: #a0a8b5;
        font-size: 10px;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================= */

    @media (max-width: 1100px) {

        .stats-grid {
            grid-template-columns:
                repeat(2, 1fr);
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


        .page-header {
            align-items: flex-start;
        }


        .panel-toolbar {
            flex-direction: column;
            align-items: stretch;
        }


        .search-form {
            width: 100%;
        }


        .search-box {
            flex: 1;
        }


        .search-input {
            width: 100%;
        }

    }


    @media (max-width: 600px) {

        .main-content {
            padding: 15px;
        }


        .page-title h1 {
            font-size: 22px;
        }


        .page-title p {
            font-size: 10px;
        }


        .add-button {
            padding: 10px;
            font-size: 0;
        }


        .add-button svg {
            width: 18px;
            height: 18px;
        }


        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }


        .stat-card {
            padding: 14px;
        }


        .stat-value {
            font-size: 20px;
        }


        .search-form {
            flex-wrap: wrap;
        }


        .search-box {
            width: 100%;
            flex: none;
        }


        .search-button,
        .clear-button {
            flex: 1;
            justify-content: center;
        }


        .low-stock-filter {
            margin-left: 0;
        }

    }


    @media (max-width: 400px) {

        .stats-grid {
            grid-template-columns: 1fr;
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
                    Products
                </h1>

                <p>
                    Manage your inventory, pricing and stock levels.
                </p>

            </div>

        </div>


        <a
            href="add.php"
            class="add-button"
        >

            <svg viewBox="0 0 24 24">
                <path d="M12 5v14m-7-7h14"/>
            </svg>

            <span>
                Add Product
            </span>

        </a>

    </header>


    <!-- STATISTICS -->

    <section class="stats-grid">


        <div class="stat-card">

            <div class="stat-label">
                Total Products
            </div>

            <div
                class="stat-value counter"
                data-value="<?= $totalProducts ?>"
            >
                0
            </div>

            <div class="stat-description">
                Products in catalog
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Total Units
            </div>

            <div
                class="stat-value counter"
                data-value="<?= $totalUnits ?>"
            >
                0
            </div>

            <div class="stat-description">
                Units currently in stock
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Inventory Value
            </div>

            <div class="stat-value">

                KSh
                <span
                    class="counter"
                    data-value="<?= $inventoryValue ?>"
                    data-decimal="2"
                >
                    0
                </span>

            </div>

            <div class="stat-description">
                Based on buying prices
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Low Stock
            </div>

            <div
                class="stat-value warning-value counter"
                data-value="<?= $lowStockCount ?>"
            >
                0
            </div>

            <div class="stat-description">
                Products need attention
            </div>

        </div>


    </section>


    <!-- PRODUCTS -->

    <section class="product-panel">


        <div class="panel-toolbar">

            <div class="toolbar-title">

                <strong>
                    Product Inventory
                </strong>

                <span>
                    <?= count($products) ?>
                    product<?= count($products) === 1 ? '' : 's' ?>
                    displayed
                </span>

            </div>


            <form
                method="GET"
                class="search-form"
                id="searchForm"
            >

                <div class="search-box">

                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-4-4"/>
                    </svg>

                    <input
                        type="text"
                        name="search"
                        id="productSearch"
                        class="search-input"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search name, SKU, category or supplier..."
                        autocomplete="off"
                    >

                </div>


                <button
                    type="submit"
                    class="search-button"
                >
                    Search
                </button>


                <?php if ($search !== '' || $lowStockOnly): ?>

                    <a
                        href="index.php"
                        class="clear-button"
                    >
                        Clear
                    </a>

                <?php endif; ?>


                <a
                    href="?filter=low_stock"
                    class="low-stock-filter <?= $lowStockOnly ? 'active' : '' ?>"
                >
                    Low Stock
                    <?php if ($lowStockCount > 0): ?>
                        (<?= $lowStockCount ?>)
                    <?php endif; ?>
                </a>

            </form>

        </div>


        <?php if (!empty($products)): ?>

            <div class="table-wrap">

                <table class="products-table">

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Supplier
                            </th>

                            <th>
                                Buying Price
                            </th>

                            <th>
                                Selling Price
                            </th>

                            <th>
                                Stock
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody id="productsTableBody">


                        <?php foreach ($products as $product): ?>

                            <?php

                            $stockQuantity =
                                (int) $product['stock_quantity'];

                            $reorderLevel =
                                (int) $product['reorder_level'];

                            $isLowStock =
                                $stockQuantity <= $reorderLevel;

                            $image =
                                trim(
                                    (string) (
                                        $product['image'] ?? ''
                                    )
                                );

                            ?>


                            <tr
                                data-product-name="<?= htmlspecialchars(
                                    strtolower($product['name'])
                                ) ?>"
                                data-product-sku="<?= htmlspecialchars(
                                    strtolower($product['sku'])
                                ) ?>"
                            >


                                <!-- PRODUCT -->

                                <td>

                                    <div class="product-cell">


                                        <?php if ($image !== ''): ?>

                                            <img
                                                src="../uploads/products/<?= htmlspecialchars($image) ?>"
                                                alt="<?= htmlspecialchars($product['name']) ?>"
                                                class="product-image"
                                                loading="lazy"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                            >

                                            <div
                                                class="product-placeholder"
                                                style="display:none;"
                                            >
                                                <?= strtoupper(
                                                    substr(
                                                        $product['name'],
                                                        0,
                                                        1
                                                    )
                                                ) ?>
                                            </div>

                                        <?php else: ?>

                                            <div class="product-placeholder">

                                                <?= strtoupper(
                                                    substr(
                                                        $product['name'],
                                                        0,
                                                        1
                                                    )
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <div class="product-name">

                                                <?= htmlspecialchars(
                                                    $product['name']
                                                ) ?>

                                            </div>

                                            <div class="product-sku">

                                                SKU:
                                                <?= htmlspecialchars(
                                                    $product['sku']
                                                ) ?>

                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- CATEGORY -->

                                <td>

                                    <span class="category">

                                        <?= htmlspecialchars(
                                            $product['category']
                                                ?: 'Uncategorized'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- SUPPLIER -->

                                <td>

                                    <span class="supplier">

                                        <?= htmlspecialchars(
                                            $product['supplier_name']
                                                ?: 'No supplier'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- BUYING -->

                                <td>

                                    <span class="price">

                                        KSh
                                        <?= number_format(
                                            (float) $product['buying_price'],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- SELLING -->

                                <td>

                                    <span class="price">

                                        KSh
                                        <?= number_format(
                                            (float) $product['selling_price'],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STOCK -->

                                <td>

                                    <span
                                        class="stock-status <?= $isLowStock ? 'low' : 'good' ?>"
                                    >

                                        <span class="stock-dot"></span>

                                        <?= $stockQuantity ?>

                                        <?php if ($isLowStock): ?>

                                            / <?= $reorderLevel ?>

                                        <?php endif; ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <a
                                            href="edit.php?id=<?= (int) $product['id'] ?>"
                                            class="action-button edit-action"
                                            title="Edit product"
                                        >

                                            <svg viewBox="0 0 24 24">

                                                <path
                                                    d="M12 20h9"
                                                />

                                                <path
                                                    d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4L16.5 3.5z"
                                                />

                                            </svg>

                                        </a>


                                        <form
                                            method="POST"
                                            action="delete.php"
                                            class="delete-form"
                                            onsubmit="return confirm('Are you sure you want to delete this product?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $product['id'] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="action-button delete-action"
                                                title="Delete product"
                                            >

                                                <svg viewBox="0 0 24 24">

                                                    <path
                                                        d="M3 6h18"
                                                    />

                                                    <path
                                                        d="M8 6V4h8v2"
                                                    />

                                                    <path
                                                        d="M19 6l-1 14H6L5 6"
                                                    />

                                                    <path
                                                        d="M10 11v5m4-5v5"
                                                    />

                                                </svg>

                                            </button>

                                        </form>


                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty-state">

                <div class="empty-icon">

                    <svg viewBox="0 0 24 24">

                        <path
                            d="M20 7.5 12 3 4 7.5v9L12 21l8-4.5v-9z"
                        />

                        <path
                            d="M12 3v9m8-4.5-8 4.5-8-4.5"
                        />

                    </svg>

                </div>


                <strong>
                    No products found
                </strong>


                <span>

                    <?php if ($search !== '' || $lowStockOnly): ?>

                        Try changing your search or filter.

                    <?php else: ?>

                        Start by adding your first product.

                    <?php endif; ?>

                </span>

            </div>


        <?php endif; ?>


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
            .forEach(function (counter) {

                const target =
                    parseFloat(
                        counter.dataset.value || 0
                    );

                const decimals =
                    parseInt(
                        counter.dataset.decimal || 0
                    );

                const duration = 900;

                const start =
                    performance.now();


                function animate(time) {

                    const progress =
                        Math.min(
                            (time - start) / duration,
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

            });


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


        /*
        |--------------------------------------------------------------------------
        | AJAX product search
        |--------------------------------------------------------------------------
        */

        const searchInput =
            document.getElementById(
                'productSearch'
            );

        const tableBody =
            document.getElementById(
                'productsTableBody'
            );


        if (
            searchInput &&
            tableBody &&
            window.jQuery
        ) {

            let searchTimer;


            searchInput.addEventListener(
                'input',
                function () {

                    clearTimeout(
                        searchTimer
                    );


                    const query =
                        searchInput.value.trim();


                    if (query.length < 2) {

                        if (query.length === 0) {
                            window.location.href =
                                'index.php';
                        }

                        return;

                    }


                    searchTimer =
                        setTimeout(
                            function () {

                                window.jQuery
                                    .getJSON(
                                        'search.php',
                                        {
                                            q: query
                                        }
                                    )
                                    .done(
                                        function (data) {

                                            if (
                                                !Array.isArray(
                                                    data
                                                )
                                            ) {
                                                return;
                                            }


                                            tableBody.innerHTML =
                                                '';


                                            data.forEach(
                                                function (
                                                    product
                                                ) {

                                                    const row =
                                                        document.createElement(
                                                            'tr'
                                                        );


                                                    const stock =
                                                        parseInt(
                                                            product.stock_quantity ||
                                                            0
                                                        );


                                                    const reorder =
                                                        parseInt(
                                                            product.reorder_level ||
                                                            0
                                                        );


                                                    const low =
                                                        stock <= reorder;


                                                    row.innerHTML = `

                                                        <td>

                                                            <div class="product-cell">

                                                                <div class="product-placeholder">

                                                                    ${(
                                                                        product.name ||
                                                                        'P'
                                                                    )
                                                                    .charAt(0)
                                                                    .toUpperCase()}

                                                                </div>

                                                                <div>

                                                                    <div class="product-name">

                                                                        ${escapeHtml(
                                                                            product.name ||
                                                                            ''
                                                                        )}

                                                                    </div>

                                                                    <div class="product-sku">

                                                                        SKU:
                                                                        ${escapeHtml(
                                                                            product.sku ||
                                                                            ''
                                                                        )}

                                                                    </div>

                                                                </div>

                                                            </div>

                                                        </td>


                                                        <td>

                                                            <span class="category">

                                                                ${escapeHtml(
                                                                    product.category ||
                                                                    'Uncategorized'
                                                                )}

                                                            </span>

                                                        </td>


                                                        <td>

                                                            <span class="supplier">

                                                                ${escapeHtml(
                                                                    product.supplier_name ||
                                                                    'No supplier'
                                                                )}

                                                            </span>

                                                        </td>


                                                        <td>

                                                            <span class="price">

                                                                KSh
                                                                ${formatNumber(
                                                                    product.buying_price
                                                                )}

                                                            </span>

                                                        </td>


                                                        <td>

                                                            <span class="price">

                                                                KSh
                                                                ${formatNumber(
                                                                    product.selling_price
                                                                )}

                                                            </span>

                                                        </td>


                                                        <td>

                                                            <span class="stock-status ${low ? 'low' : 'good'}">

                                                                <span class="stock-dot"></span>

                                                                ${stock}

                                                                ${low ? '/ ' + reorder : ''}

                                                            </span>

                                                        </td>


                                                        <td>

                                                            <div class="actions">

                                                                <a
                                                                    href="edit.php?id=${parseInt(product.id)}"
                                                                    class="action-button edit-action"
                                                                >

                                                                    <svg viewBox="0 0 24 24">

                                                                        <path d="M12 20h9"/>

                                                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4L16.5 3.5z"/>

                                                                    </svg>

                                                                </a>

                                                            </div>

                                                        </td>

                                                    `;


                                                    tableBody.appendChild(
                                                        row
                                                    );

                                                }
                                            );

                                        }
                                    );

                            },
                            300
                        );

                }
            );

        }


        function escapeHtml(value) {

            const div =
                document.createElement(
                    'div'
                );

            div.textContent =
                value ?? '';

            return div.innerHTML;

        }


        function formatNumber(value) {

            return Number(
                value || 0
            ).toLocaleString(
                'en-KE',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

        }

    }
);

</script>

</body>

</html>
