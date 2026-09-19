<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$error = '';

$customers = $pdo
    ->query("
        SELECT id, name
        FROM customers
        ORDER BY name ASC
    ")
    ->fetchAll();

$products = $pdo
    ->query("
        SELECT
            id,
            name,
            sku,
            selling_price,
            stock_quantity
        FROM products
        WHERE stock_quantity > 0
        ORDER BY name ASC
    ")
    ->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Invalid security token. Please refresh the page and try again.';

    } else {

        $customer_id =
            (int) ($_POST['customer_id'] ?? 0);

        $product_ids =
            $_POST['product_id'] ?? [];

        $quantities =
            $_POST['quantity'] ?? [];

        if (
            !is_array($product_ids) ||
            !is_array($quantities) ||
            empty($product_ids)
        ) {

            $error =
                'Please add at least one product.';

        } elseif (
            count($product_ids) !== count($quantities)
        ) {

            $error =
                'Invalid sale item data. Please try again.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Validate customer
                |--------------------------------------------------------------------------
                */

                if ($customer_id > 0) {

                    $customerStmt = $pdo->prepare("
                        SELECT id
                        FROM customers
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $customerStmt->execute([
                        $customer_id
                    ]);

                    if (!$customerStmt->fetch()) {

                        throw new Exception(
                            'Selected customer does not exist.'
                        );
                    }
                }

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Aggregate duplicate products
                |--------------------------------------------------------------------------
                */

                $requestedProducts = [];

                foreach ($product_ids as $index => $product_id) {

                    $product_id =
                        (int) $product_id;

                    $quantity =
                        (int) (
                            $quantities[$index] ?? 0
                        );

                    if (
                        $product_id <= 0 ||
                        $quantity <= 0
                    ) {

                        throw new Exception(
                            'Invalid product or quantity.'
                        );
                    }

                    if (
                        !isset(
                            $requestedProducts[$product_id]
                        )
                    ) {

                        $requestedProducts[$product_id] = [
                            'product_id' => $product_id,
                            'quantity' => 0
                        ];
                    }

                    $requestedProducts[$product_id]['quantity']
                        += $quantity;
                }

                /*
                |--------------------------------------------------------------------------
                | Validate products and lock rows
                |--------------------------------------------------------------------------
                */

                $stockStmt = $pdo->prepare("
                    SELECT
                        id,
                        name,
                        selling_price,
                        stock_quantity
                    FROM products
                    WHERE id = ?
                    FOR UPDATE
                ");

                $items = [];

                $total_amount = 0;

                foreach (
                    $requestedProducts as $requested
                ) {

                    $product_id =
                        $requested['product_id'];

                    $quantity =
                        $requested['quantity'];

                    $stockStmt->execute([
                        $product_id
                    ]);

                    $product =
                        $stockStmt->fetch();

                    if (!$product) {

                        throw new Exception(
                            'One of the selected products does not exist.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Final stock check
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $quantity >
                        (int) $product['stock_quantity']
                    ) {

                        throw new Exception(
                            'Insufficient stock for ' .
                            $product['name'] .
                            '. Available: ' .
                            $product['stock_quantity'] .
                            ', Requested: ' .
                            $quantity
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Server-authoritative selling price
                    |--------------------------------------------------------------------------
                    */

                    $unit_price =
                        (float) $product['selling_price'];

                    if ($unit_price < 0) {

                        throw new Exception(
                            'Invalid selling price for ' .
                            $product['name'] .
                            '.'
                        );
                    }

                    $subtotal =
                        $quantity * $unit_price;

                    $total_amount +=
                        $subtotal;

                    $items[] = [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'subtotal' => $subtotal
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Validate total
                |--------------------------------------------------------------------------
                */

                if ($total_amount <= 0) {

                    throw new Exception(
                        'Sale total must be greater than zero.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Create sale
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO sales (
                        customer_id,
                        user_id,
                        total_amount
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $customer_id > 0
                        ? $customer_id
                        : null,

                    $_SESSION['user_id'],

                    $total_amount
                ]);

                $sale_id =
                    $pdo->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | Insert sale items
                |--------------------------------------------------------------------------
                */

                $itemStmt = $pdo->prepare("
                    INSERT INTO sale_items (
                        sale_id,
                        product_id,
                        quantity,
                        unit_price,
                        subtotal
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                /*
                |--------------------------------------------------------------------------
                | Safely reduce stock
                |--------------------------------------------------------------------------
                */

                $stockUpdateStmt = $pdo->prepare("
                    UPDATE products
                    SET stock_quantity =
                        stock_quantity - ?
                    WHERE id = ?
                      AND stock_quantity >= ?
                ");

                foreach ($items as $item) {

                    $itemStmt->execute([
                        $sale_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['subtotal']
                    ]);

                    $stockUpdateStmt->execute([
                        $item['quantity'],
                        $item['product_id'],
                        $item['quantity']
                    ]);

                    if (
                        $stockUpdateStmt->rowCount()
                        !== 1
                    ) {

                        throw new Exception(
                            'Stock could not be updated safely.'
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Commit transaction
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                header(
                    'Location: index.php?success=1'
                );

                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (function_exists('logError')) {
                    logError(
                        'Sale creation failed.',
                        $e
                    );
                }

                $error =
                    'Sale could not be saved. Please check the details and try again.';
            }
        }
    }
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

    <title>Record Sale | SoftgeniousDev</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;
            background:
                linear-gradient(
                    135deg,
                    #f5f8fc 0%,
                    #eef3f8 100%
                );
            color: #172033;
        }

        .page {
            min-height: 100vh;
            padding: 42px 42px 60px;
        }

        .content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #526071;
            font-size: 14px;
            font-weight: 700;
            transition: .2s ease;
        }

        .back-link:hover {
            color: #2563eb;
            transform: translateX(-2px);
        }

        .page-heading h1 {
            margin: 16px 0 0;
            font-size: 30px;
            letter-spacing: -.7px;
        }

        .page-heading p {
            margin: 7px 0 0;
            color: #738094;
            font-size: 14px;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            background: #fff;
            border: 1px solid #e4eaf1;
            border-radius: 999px;
            color: #526071;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 6px 18px rgba(30, 50, 80, .05);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow:
                0 0 0 5px rgba(34, 197, 94, .10);
        }

        .alert {
            padding: 14px 17px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .alert-error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #be123c;
        }

        .main-card {
            background: rgba(255, 255, 255, .96);
            border: 1px solid #e3e9f1;
            border-radius: 20px;
            box-shadow:
                0 18px 50px rgba(30, 50, 80, .08);
            overflow: hidden;
        }

        .card-header {
            padding: 24px 28px;
            border-bottom: 1px solid #edf1f5;
        }

        .section-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }

        .section-subtitle {
            margin: 5px 0 0;
            color: #7a8798;
            font-size: 13px;
        }

        .form-body {
            padding: 28px;
        }

        .customer-box {
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #e7edf4;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .field-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 800;
            color: #344054;
        }

        select,
        input {
            width: 100%;
            height: 44px;
            border: 1px solid #d9e1ea;
            border-radius: 10px;
            padding: 0 12px;
            background: #fff;
            color: #1f2937;
            font-size: 14px;
            outline: none;
            transition: .2s ease;
        }

        select:focus,
        input:focus {
            border-color: #2563eb;
            box-shadow:
                0 0 0 4px rgba(37, 99, 235, .09);
        }

        .customer-select {
            max-width: 520px;
        }

        .items-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 14px;
        }

        .items-heading h2 {
            margin: 0;
            font-size: 17px;
        }

        .items-heading span {
            display: block;
            margin-top: 5px;
            color: #7a8798;
            font-size: 13px;
        }

        .add-btn {
            height: 40px;
            padding: 0 15px;
            border: none;
            border-radius: 9px;
            background: #172033;
            color: #fff;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s ease;
            white-space: nowrap;
        }

        .add-btn:hover {
            transform: translateY(-1px);
            box-shadow:
                0 8px 18px rgba(23, 32, 51, .16);
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e4eaf1;
            border-radius: 14px;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        th {
            padding: 14px 15px;
            background: #f7f9fc;
            border-bottom: 1px solid #e4eaf1;
            text-align: left;
            color: #657286;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        td {
            padding: 13px 15px;
            border-bottom: 1px solid #edf1f5;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: .2s ease;
        }

        tbody tr:hover {
            background: #fafcff;
        }

        .product-select {
            min-width: 280px;
        }

        .quantity-input {
            min-width: 100px;
        }

        .price-input {
            min-width: 130px;
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
            cursor: default;
        }

        .available {
            min-width: 90px;
            font-weight: 800;
            color: #475569;
            white-space: nowrap;
        }

        .subtotal {
            font-weight: 800;
            color: #172033;
            white-space: nowrap;
        }

        .remove-btn {
            height: 38px;
            padding: 0 13px;
            border: 1px solid #e2e7ee;
            border-radius: 9px;
            background: #fff;
            color: #64748b;
            cursor: pointer;
            font-weight: 700;
            transition: .2s ease;
        }

        .remove-btn:hover {
            background: #fff1f2;
            color: #dc2626;
            border-color: #fecdd3;
        }

        .empty-state {
            text-align: center;
            padding: 35px 20px;
            color: #7a8798;
            font-size: 14px;
        }

        .summary {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
        }

        .total-box {
            min-width: 300px;
            padding: 20px 22px;
            border-radius: 15px;
            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #f8fafc
                );
            border: 1px solid #dbeafe;
        }

        .total-label {
            color: #718096;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .total-value {
            margin-top: 5px;
            font-size: 29px;
            font-weight: 900;
            letter-spacing: -.8px;
        }

        .info-note {
            margin-top: 18px;
            padding: 13px 15px;
            border-radius: 10px;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            color: #475569;
            font-size: 12px;
            line-height: 1.6;
        }

        .actions {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #edf1f5;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .cancel-btn,
        .save-btn {
            height: 44px;
            padding: 0 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .cancel-btn {
            background: #fff;
            color: #526071;
            border: 1px solid #dce3eb;
        }

        .cancel-btn:hover {
            background: #f8fafc;
        }

        .save-btn {
            border: none;
            background: #2563eb;
            color: #fff;
            box-shadow:
                0 8px 20px rgba(37, 99, 235, .18);
            transition: .2s ease;
        }

        .save-btn:hover {
            transform: translateY(-1px);
            box-shadow:
                0 11px 25px rgba(37, 99, 235, .25);
        }

        .save-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 800px) {

            .page {
                padding: 25px 16px 45px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .header-badge {
                display: none;
            }

            .page-heading h1 {
                font-size: 25px;
            }

            .card-header,
            .form-body {
                padding: 20px;
            }

            .items-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .add-btn {
                width: 100%;
            }

            .summary {
                justify-content: stretch;
            }

            .total-box {
                width: 100%;
                min-width: 0;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .cancel-btn,
            .save-btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <div class="content">

        <div class="topbar">

            <div>

                <a
                    href="index.php"
                    class="back-link"
                >
                    ← Back to Sales
                </a>

                <div class="page-heading">

                    <h1>Record Sale</h1>

                    <p>
                        Create a customer sale and automatically update stock.
                    </p>

                </div>

            </div>

            <div class="header-badge">

                <span class="status-dot"></span>

                Sales System

            </div>

        </div>

        <?php if ($error): ?>

            <div class="alert alert-error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <div class="main-card">

            <div class="card-header">

                <h2 class="section-title">
                    Sale Details
                </h2>

                <p class="section-subtitle">
                    Select a customer and add the products being sold.
                </p>

            </div>

            <div class="form-body">

                <form
                    method="POST"
                    id="saleForm"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token()) ?>"
                    >

                    <div class="customer-box">

                        <label
                            for="customer_id"
                            class="field-label"
                        >
                            Customer
                        </label>

                        <select
                            name="customer_id"
                            id="customer_id"
                            class="customer-select"
                        >

                            <option value="">
                                Walk-in Customer
                            </option>

                            <?php foreach ($customers as $customer): ?>

                                <option
                                    value="<?= (int) $customer['id'] ?>"
                                    <?= (
                                        isset($_POST['customer_id']) &&
                                        (int) $_POST['customer_id'] ===
                                        (int) $customer['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $customer['name']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="items-heading">

                        <div>

                            <h2>
                                Sale Items
                            </h2>

                            <span>
                                Only products currently in stock are available.
                            </span>

                        </div>

                        <button
                            type="button"
                            class="add-btn"
                            onclick="addProductRow()"
                        >
                            + Add Product
                        </button>

                    </div>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Available
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Unit Price
                                    </th>

                                    <th>
                                        Subtotal
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody id="saleItems"></tbody>

                        </table>

                        <div
                            id="emptyState"
                            class="empty-state"
                            style="display:none;"
                        >
                            No products added yet.
                        </div>

                    </div>

                    <div class="summary">

                        <div class="total-box">

                            <div class="total-label">
                                Sale Total
                            </div>

                            <div class="total-value">

                                KSh
                                <span id="grandTotal">
                                    0.00
                                </span>

                            </div>

                        </div>

                    </div>

                    <div class="info-note">

                        Selling prices are loaded from the database.
                        The final sale price is always verified on the server
                        before the sale is saved.

                    </div>

                    <div class="actions">

                        <a
                            href="index.php"
                            class="cancel-btn"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="save-btn"
                            id="saveButton"
                        >
                            Save Sale
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<script>

const products =
    <?= json_encode(
        $products,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

function escapeHtml(value) {

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function addProductRow() {

    const tbody =
        document.getElementById('saleItems');

    const emptyState =
        document.getElementById('emptyState');

    const row =
        document.createElement('tr');

    let options =
        '<option value="">Select product</option>';

    products.forEach(product => {

        options += `
            <option
                value="${escapeHtml(product.id)}"
                data-price="${escapeHtml(product.selling_price)}"
                data-stock="${escapeHtml(product.stock_quantity)}"
            >
                ${escapeHtml(product.name)}
                (${escapeHtml(product.sku)})
            </option>
        `;

    });

    row.innerHTML = `

        <td>

            <select
                name="product_id[]"
                class="product-select"
                onchange="updateProduct(this)"
                required
            >

                ${options}

            </select>

        </td>

        <td class="available">
            0
        </td>

        <td>

            <input
                type="number"
                name="quantity[]"
                class="quantity-input"
                min="1"
                value="1"
                oninput="calculateTotal()"
                required
            >

        </td>

        <td>

            <input
                type="number"
                class="price-input"
                value="0.00"
                readonly
                tabindex="-1"
            >

        </td>

        <td class="subtotal">
            KSh 0.00
        </td>

        <td>

            <button
                type="button"
                class="remove-btn"
                onclick="removeRow(this)"
            >
                Remove
            </button>

        </td>

    `;

    tbody.appendChild(row);

    emptyState.style.display = 'none';

    calculateTotal();
}

function updateProduct(select) {

    const row =
        select.closest('tr');

    const option =
        select.options[
            select.selectedIndex
        ];

    const price =
        option.getAttribute('data-price');

    const stock =
        option.getAttribute('data-stock');

    const priceInput =
        row.querySelector('.price-input');

    const available =
        row.querySelector('.available');

    const quantityInput =
        row.querySelector(
            'input[name="quantity[]"]'
        );

    if (price !== null) {

        priceInput.value =
            Number(price).toFixed(2);

    } else {

        priceInput.value =
            '0.00';

    }

    if (stock !== null) {

        available.textContent =
            stock;

        quantityInput.max =
            stock;

        if (
            Number(quantityInput.value) >
            Number(stock)
        ) {
            quantityInput.value = stock;
        }

    } else {

        available.textContent =
            '0';

        quantityInput.removeAttribute('max');
    }

    calculateTotal();
}

function calculateTotal() {

    let total = 0;

    document
        .querySelectorAll('#saleItems tr')
        .forEach(row => {

            const quantity =
                parseFloat(
                    row.querySelector(
                        'input[name="quantity[]"]'
                    ).value
                ) || 0;

            const price =
                parseFloat(
                    row.querySelector(
                        '.price-input'
                    ).value
                ) || 0;

            const subtotal =
                quantity * price;

            row.querySelector(
                '.subtotal'
            ).textContent =
                'KSh ' + subtotal.toFixed(2);

            total += subtotal;

        });

    document.getElementById(
        'grandTotal'
    ).textContent =
        total.toFixed(2);
}

function removeRow(button) {

    const row =
        button.closest('tr');

    if (row) {
        row.remove();
    }

    const rows =
        document.querySelectorAll(
            '#saleItems tr'
        );

    if (rows.length === 0) {

        document.getElementById(
            'emptyState'
        ).style.display = 'block';

    }

    calculateTotal();
}

document
    .getElementById('saleForm')
    .addEventListener('submit', function(event) {

        const rows =
            document.querySelectorAll(
                '#saleItems tr'
            );

        if (rows.length === 0) {

            event.preventDefault();

            alert(
                'Please add at least one product before saving the sale.'
            );

            return;
        }

        let valid = true;

        rows.forEach(row => {

            const product =
                row.querySelector(
                    'select[name="product_id[]"]'
                ).value;

            const quantity =
                parseInt(
                    row.querySelector(
                        'input[name="quantity[]"]'
                    ).value,
                    10
                ) || 0;

            const available =
                parseInt(
                    row.querySelector(
                        '.available'
                    ).textContent,
                    10
                ) || 0;

            if (
                !product ||
                quantity <= 0 ||
                quantity > available
            ) {
                valid = false;
            }

        });

        if (!valid) {

            event.preventDefault();

            alert(
                'Please check the selected products and quantities. Quantity cannot exceed available stock.'
            );

            return;
        }

        document.getElementById(
            'saveButton'
        ).disabled = true;

        document.getElementById(
            'saveButton'
        ).textContent =
            'Saving Sale...';

    });

addProductRow();

</script>

</body>

</html>