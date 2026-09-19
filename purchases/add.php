<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$message = '';
$error = '';

$suppliers = $pdo
    ->query("
        SELECT id, name
        FROM suppliers
        ORDER BY name ASC
    ")
    ->fetchAll();

$products = $pdo
    ->query("
        SELECT id, name, sku, buying_price, stock_quantity
        FROM products
        ORDER BY name ASC
    ")
    ->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Invalid security token. Please refresh the page and try again.';

    } else {

        $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
        $product_ids = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unit_costs = $_POST['unit_cost'] ?? [];

        if ($supplier_id <= 0) {

            $error = 'Please select a supplier.';

        } elseif (empty($product_ids)) {

            $error = 'Please add at least one product.';

        } elseif (
            count($product_ids) !== count($quantities) ||
            count($product_ids) !== count($unit_costs)
        ) {

            $error = 'Invalid purchase item data. Please try again.';

        } else {

            try {

                $supplierStmt = $pdo->prepare("
                    SELECT id
                    FROM suppliers
                    WHERE id = ?
                    LIMIT 1
                ");

                $supplierStmt->execute([
                    $supplier_id
                ]);

                if (!$supplierStmt->fetch()) {
                    throw new Exception(
                        'Selected supplier does not exist.'
                    );
                }

                $pdo->beginTransaction();

                $total_amount = 0;
                $items = [];

                foreach ($product_ids as $index => $product_id) {

                    $product_id = (int) $product_id;
                    $quantity = (int) ($quantities[$index] ?? 0);
                    $unit_cost = (float) ($unit_costs[$index] ?? 0);

                    if (
                        $product_id <= 0 ||
                        $quantity <= 0 ||
                        $unit_cost < 0
                    ) {
                        throw new Exception(
                            'Invalid product, quantity or unit cost.'
                        );
                    }

                    $productStmt = $pdo->prepare("
                        SELECT id
                        FROM products
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $productStmt->execute([
                        $product_id
                    ]);

                    if (!$productStmt->fetch()) {
                        throw new Exception(
                            'One of the selected products does not exist.'
                        );
                    }

                    $subtotal = $quantity * $unit_cost;

                    $total_amount += $subtotal;

                    $items[] = [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'unit_cost' => $unit_cost,
                        'subtotal' => $subtotal
                    ];
                }

                if ($total_amount <= 0) {

                    throw new Exception(
                        'Purchase total must be greater than zero.'
                    );
                }

                $stmt = $pdo->prepare("
                    INSERT INTO purchases (
                        supplier_id,
                        user_id,
                        total_amount
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $supplier_id,
                    $_SESSION['user_id'],
                    $total_amount
                ]);

                $purchase_id = $pdo->lastInsertId();

                $itemStmt = $pdo->prepare("
                    INSERT INTO purchase_items (
                        purchase_id,
                        product_id,
                        quantity,
                        unit_cost,
                        subtotal
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stockStmt = $pdo->prepare("
                    UPDATE products
                    SET stock_quantity = stock_quantity + ?
                    WHERE id = ?
                ");

                foreach ($items as $item) {

                    $itemStmt->execute([
                        $purchase_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_cost'],
                        $item['subtotal']
                    ]);

                    $stockStmt->execute([
                        $item['quantity'],
                        $item['product_id']
                    ]);

                    if ($stockStmt->rowCount() !== 1) {

                        throw new Exception(
                            'Unable to update product stock.'
                        );
                    }
                }

                $pdo->commit();

                header('Location: index.php?success=1');
                exit;

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (function_exists('logError')) {
                    logError(
                        'Purchase creation failed.',
                        $e
                    );
                }

                $error =
                    'Purchase could not be saved. Please check the details and try again.';
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

    <title>Record Purchase - SoftgeniousDev</title>

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
            max-width: 1180px;
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
            margin: 0;
            font-size: 30px;
            letter-spacing: -0.7px;
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
            background: #ffffff;
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
            box-shadow: 0 0 0 5px rgba(34, 197, 94, .10);
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
            box-shadow: 0 18px 50px rgba(30, 50, 80, .08);
            overflow: hidden;
        }

        .card-header {
            padding: 24px 28px;
            border-bottom: 1px solid #edf1f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
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

        .supplier-box {
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

        .required {
            color: #ef4444;
        }

        select,
        input {
            width: 100%;
            height: 44px;
            border: 1px solid #d9e1ea;
            border-radius: 10px;
            padding: 0 12px;
            background: #ffffff;
            color: #1f2937;
            font-size: 14px;
            outline: none;
            transition: .2s ease;
        }

        select:focus,
        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .09);
        }

        .supplier-select {
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
            color: #7a8798;
            font-size: 13px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e4eaf1;
            border-radius: 14px;
        }

        table {
            width: 100%;
            min-width: 820px;
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

        .cost-input {
            min-width: 130px;
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
            background: #ffffff;
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

        .add-btn {
            height: 40px;
            padding: 0 15px;
            border: none;
            border-radius: 9px;
            background: #172033;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s ease;
        }

        .add-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(23, 32, 51, .16);
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
                    #f8fafc,
                    #f1f5f9
                );
            border: 1px solid #e2e8f0;
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
            background: #ffffff;
            color: #526071;
            border: 1px solid #dce3eb;
        }

        .cancel-btn:hover {
            background: #f8fafc;
        }

        .save-btn {
            border: none;
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, .18);
            transition: .2s ease;
        }

        .save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 11px 25px rgba(37, 99, 235, .25);
        }

        .save-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
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

            .card-header {
                padding: 20px;
            }

            .form-body {
                padding: 20px;
            }

            .items-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .summary {
                justify-content: stretch;
            }

            .total-box {
                width: 100%;
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
                    ← Back to Purchases
                </a>

                <div class="page-heading" style="margin-top: 16px;">

                    <h1>Record Purchase</h1>

                    <p>
                        Add incoming stock and update your inventory.
                    </p>

                </div>

            </div>

            <div class="header-badge">

                <span class="status-dot"></span>

                Inventory System

            </div>

        </div>

        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <div class="main-card">

            <div class="card-header">

                <div>

                    <h2 class="section-title">
                        Purchase Details
                    </h2>

                    <p class="section-subtitle">
                        Select the supplier and add the products received.
                    </p>

                </div>

            </div>

            <div class="form-body">

                <form
                    method="POST"
                    id="purchaseForm"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token()) ?>"
                    >

                    <div class="supplier-box">

                        <label
                            for="supplier_id"
                            class="field-label"
                        >
                            Supplier
                            <span class="required">*</span>
                        </label>

                        <select
                            name="supplier_id"
                            id="supplier_id"
                            class="supplier-select"
                            required
                        >

                            <option value="">
                                Select supplier
                            </option>

                            <?php foreach ($suppliers as $supplier): ?>

                                <option
                                    value="<?= (int) $supplier['id'] ?>"
                                    <?= (
                                        isset($_POST['supplier_id']) &&
                                        (int) $_POST['supplier_id'] ===
                                        (int) $supplier['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="items-heading">

                        <div>

                            <h2>
                                Purchase Items
                            </h2>

                            <span>
                                Add every product included in this purchase.
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
                                        Quantity
                                    </th>

                                    <th>
                                        Unit Cost
                                    </th>

                                    <th>
                                        Subtotal
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody id="purchaseItems"></tbody>

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
                                Purchase Total
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

                        Product buying prices are automatically loaded when
                        you select a product. You can adjust the unit cost
                        if the actual purchase price differs.

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
                            Save Purchase
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<script>

const products = <?= json_encode(
    $products,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
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
        document.getElementById('purchaseItems');

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
                data-price="${escapeHtml(product.buying_price)}"
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
                onchange="updatePrice(this)"
                required
            >
                ${options}
            </select>

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
                name="unit_cost[]"
                class="cost-input"
                min="0"
                step="0.01"
                value="0"
                oninput="calculateTotal()"
                required
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

function updatePrice(select) {

    const row =
        select.closest('tr');

    const selectedOption =
        select.options[select.selectedIndex];

    const price =
        selectedOption.getAttribute('data-price');

    const costInput =
        row.querySelector(
            'input[name="unit_cost[]"]'
        );

    if (price !== null) {
        costInput.value =
            parseFloat(price).toFixed(2);
    }

    calculateTotal();
}

function calculateTotal() {

    let total = 0;

    document
        .querySelectorAll('#purchaseItems tr')
        .forEach(row => {

            const quantity =
                parseFloat(
                    row.querySelector(
                        'input[name="quantity[]"]'
                    ).value
                ) || 0;

            const unitCost =
                parseFloat(
                    row.querySelector(
                        'input[name="unit_cost[]"]'
                    ).value
                ) || 0;

            const subtotal =
                quantity * unitCost;

            const subtotalElement =
                row.querySelector('.subtotal');

            subtotalElement.textContent =
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
            '#purchaseItems tr'
        );

    if (rows.length === 0) {

        document.getElementById(
            'emptyState'
        ).style.display = 'block';

    }

    calculateTotal();
}

document
    .getElementById('purchaseForm')
    .addEventListener('submit', function(event) {

        const rows =
            document.querySelectorAll(
                '#purchaseItems tr'
            );

        if (rows.length === 0) {

            event.preventDefault();

            alert(
                'Please add at least one product before saving the purchase.'
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
                parseFloat(
                    row.querySelector(
                        'input[name="quantity[]"]'
                    ).value
                ) || 0;

            const cost =
                parseFloat(
                    row.querySelector(
                        'input[name="unit_cost[]"]'
                    ).value
                );

            if (
                !product ||
                quantity <= 0 ||
                Number.isNaN(cost) ||
                cost < 0
            ) {
                valid = false;
            }

        });

        if (!valid) {

            event.preventDefault();

            alert(
                'Please check all product, quantity and unit cost fields.'
            );

            return;
        }

        document.getElementById(
            'saveButton'
        ).disabled = true;

        document.getElementById(
            'saveButton'
        ).textContent = 'Saving Purchase...';

    });

// Start with one product row.
addProductRow();

</script>

</body>

</html>