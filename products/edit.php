<?php

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/csrf.php';

$errors = [];

// Get product ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    die('Invalid product ID.');
}

// Get product
$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    die('Product not found.');
}

// Form values
$name = $product['name'];
$sku = $product['sku'];
$category = $product['category'] ?? '';
$supplierId = $product['supplier_id'] ?? '';
$buyingPrice = $product['buying_price'];
$sellingPrice = $product['selling_price'];
$stockQuantity = $product['stock_quantity'];
$reorderLevel = $product['reorder_level'];

// Get suppliers
$suppliers = $pdo
    ->query("
        SELECT id, name
        FROM suppliers
        ORDER BY name ASC
    ")
    ->fetchAll();

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }

    // Get submitted values
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $supplierId = $_POST['supplier_id'] ?? '';
    $buyingPrice = $_POST['buying_price'] ?? '';
    $sellingPrice = $_POST['selling_price'] ?? '';
    $stockQuantity = $_POST['stock_quantity'] ?? '';
    $reorderLevel = $_POST['reorder_level'] ?? '';

    // Validate name
    if ($name === '') {
        $errors[] = 'Product name is required.';
    }

    // Validate SKU
    if ($sku === '') {
        $errors[] = 'SKU is required.';
    }

    // Validate prices
    if (
        $buyingPrice === '' ||
        !is_numeric($buyingPrice) ||
        (float) $buyingPrice < 0
    ) {
        $errors[] = 'Buying price must be a valid number.';
    }

    if (
        $sellingPrice === '' ||
        !is_numeric($sellingPrice) ||
        (float) $sellingPrice < 0
    ) {
        $errors[] = 'Selling price must be a valid number.';
    }

    // Validate stock
    if (
        $stockQuantity === '' ||
        filter_var($stockQuantity, FILTER_VALIDATE_INT) === false ||
        (int) $stockQuantity < 0
    ) {
        $errors[] = 'Stock quantity must be a valid whole number.';
    }

    // Validate reorder level
    if (
        $reorderLevel === '' ||
        filter_var($reorderLevel, FILTER_VALIDATE_INT) === false ||
        (int) $reorderLevel < 0
    ) {
        $errors[] = 'Reorder level must be a valid whole number.';
    }

    // Validate supplier
    if ($supplierId !== '') {

        if (
            filter_var($supplierId, FILTER_VALIDATE_INT) === false ||
            (int) $supplierId <= 0
        ) {
            $errors[] = 'Invalid supplier selected.';
        } else {

            $supplierCheck = $pdo->prepare("
                SELECT id
                FROM suppliers
                WHERE id = ?
                LIMIT 1
            ");

            $supplierCheck->execute([
                (int) $supplierId
            ]);

            if (!$supplierCheck->fetch()) {
                $errors[] = 'Selected supplier does not exist.';
            }
        }
    }

    // Check duplicate SKU
    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE sku = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $sku,
            $id
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Another product already uses this SKU.';
        }
    }

    // Update product
    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                UPDATE products
                SET
                    name = ?,
                    sku = ?,
                    category = ?,
                    supplier_id = ?,
                    buying_price = ?,
                    selling_price = ?,
                    stock_quantity = ?,
                    reorder_level = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $sku,
                $category !== '' ? $category : null,
                $supplierId !== '' ? (int) $supplierId : null,
                (float) $buyingPrice,
                (float) $sellingPrice,
                (int) $stockQuantity,
                (int) $reorderLevel,
                $id
            ]);

            header('Location: index.php');
            exit;

        } catch (PDOException $e) {

            if (($e->errorInfo[1] ?? null) === 1062) {
                $errors[] = 'Another product already uses this SKU.';
            } else {
                $errors[] =
                    'Unable to update the product. Please try again later.';
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

    <title>Edit Product | SoftgeniousDev</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #172033;
        }

        .container {
            max-width: 850px;
            margin: 40px auto;
            padding: 20px;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            color: #667085;
        }

        .card {
            background: white;
            border: 1px solid #e4e7ec;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 18px rgba(16, 24, 40, 0.05);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .field {
            display: flex;
            flex-direction: column;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            margin-bottom: 7px;
            font-size: 14px;
        }

        input,
        select {
            padding: 12px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #1570ef;
        }

        .error-box {
            background: #fef3f2;
            border: 1px solid #fecdca;
            color: #b42318;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .actions {
            margin-top: 25px;
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-primary {
            background: #1570ef;
            color: white;
        }

        .btn-secondary {
            background: #e4e7ec;
            color: #344054;
        }

        @media (max-width: 650px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .card {
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Edit Product</h1>

        <p>
            Update product information and inventory details.
        </p>

    </div>

    <div class="card">

        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <form method="POST">

            <!-- CSRF protection -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()) ?>"
            >

            <div class="form-grid">

                <div class="field full">

                    <label for="name">
                        Product Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($name) ?>"
                        required
                    >

                </div>

                <div class="field">

                    <label for="sku">
                        SKU
                    </label>

                    <input
                        type="text"
                        id="sku"
                        name="sku"
                        value="<?= htmlspecialchars($sku) ?>"
                        required
                    >

                </div>

                <div class="field">

                    <label for="category">
                        Category
                    </label>

                    <input
                        type="text"
                        id="category"
                        name="category"
                        value="<?= htmlspecialchars($category) ?>"
                    >

                </div>

                <div class="field full">

                    <label for="supplier_id">
                        Supplier
                    </label>

                    <select
                        id="supplier_id"
                        name="supplier_id"
                    >

                        <option value="">
                            No supplier
                        </option>

                        <?php foreach ($suppliers as $supplier): ?>

                            <option
                                value="<?= (int) $supplier['id'] ?>"
                                <?= (string) $supplierId === (string) $supplier['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars($supplier['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="field">

                    <label for="buying_price">
                        Buying Price
                    </label>

                    <input
                        type="number"
                        id="buying_price"
                        name="buying_price"
                        value="<?= htmlspecialchars($buyingPrice) ?>"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>

                <div class="field">

                    <label for="selling_price">
                        Selling Price
                    </label>

                    <input
                        type="number"
                        id="selling_price"
                        name="selling_price"
                        value="<?= htmlspecialchars($sellingPrice) ?>"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>

                <div class="field">

                    <label for="stock_quantity">
                        Stock Quantity
                    </label>

                    <input
                        type="number"
                        id="stock_quantity"
                        name="stock_quantity"
                        value="<?= htmlspecialchars($stockQuantity) ?>"
                        min="0"
                        step="1"
                        required
                    >

                </div>

                <div class="field">

                    <label for="reorder_level">
                        Reorder Level
                    </label>

                    <input
                        type="number"
                        id="reorder_level"
                        name="reorder_level"
                        value="<?= htmlspecialchars($reorderLevel) ?>"
                        min="0"
                        step="1"
                        required
                    >

                </div>

            </div>

            <div class="actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Product
                </button>

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>

**Test:** open an existing product → **Edit** → change something → **Update Product**. It should save normally.

When that works, say **done** and we'll secure **`products/delete.php`** next.
