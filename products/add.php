<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$errors = [];

$name = '';
$sku = '';
$category = '';
$supplier_id = '';
$buying_price = '';
$selling_price = '';
$stock_quantity = '';
$reorder_level = '5';

try {

    $supplierStmt = $pdo->query("
        SELECT id, name
        FROM suppliers
        ORDER BY name ASC
    ");

    $suppliers = $supplierStmt->fetchAll();

} catch (Throwable $e) {

    logError(
        'Failed to load suppliers on add product page.',
        $e
    );

    $suppliers = [];
    $errors[] = 'Unable to load suppliers. Please try again.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $errors[] = 'Invalid security token. Please refresh the page and try again.';

    } else {

        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $supplier_id = trim($_POST['supplier_id'] ?? '');
        $buying_price = trim($_POST['buying_price'] ?? '');
        $selling_price = trim($_POST['selling_price'] ?? '');
        $stock_quantity = trim($_POST['stock_quantity'] ?? '');
        $reorder_level = trim($_POST['reorder_level'] ?? '5');


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($name === '') {
            $errors[] = 'Product name is required.';
        }

        if ($sku === '') {
            $errors[] = 'SKU is required.';
        }

        if ($buying_price === '' || !is_numeric($buying_price) || $buying_price < 0) {
            $errors[] = 'Enter a valid buying price.';
        }

        if ($selling_price === '' || !is_numeric($selling_price) || $selling_price < 0) {
            $errors[] = 'Enter a valid selling price.';
        }

        if ($stock_quantity === '' || filter_var($stock_quantity, FILTER_VALIDATE_INT) === false || $stock_quantity < 0) {
            $errors[] = 'Enter a valid stock quantity.';
        }

        if ($reorder_level === '' || filter_var($reorder_level, FILTER_VALIDATE_INT) === false || $reorder_level < 0) {
            $errors[] = 'Enter a valid reorder level.';
        }


        /*
        |--------------------------------------------------------------------------
        | Supplier Validation
        |--------------------------------------------------------------------------
        */

        if ($supplier_id !== '') {

            if (
                filter_var($supplier_id, FILTER_VALIDATE_INT) === false ||
                (int) $supplier_id <= 0
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
                    (int) $supplier_id
                ]);

                if (!$supplierCheck->fetch()) {
                    $errors[] = 'Selected supplier does not exist.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Image Validation
        |--------------------------------------------------------------------------
        */

        $uploadedImage = null;

        if (
            isset($_FILES['image']) &&
            $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

                $errors[] = 'There was a problem uploading the product image.';

            } else {

                $maxSize = 2 * 1024 * 1024;

                if ($_FILES['image']['size'] > $maxSize) {

                    $errors[] = 'Product image must not exceed 2MB.';

                } else {

                    $finfo = new finfo(FILEINFO_MIME_TYPE);

                    $mimeType = $finfo->file(
                        $_FILES['image']['tmp_name']
                    );

                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($allowedTypes[$mimeType])) {

                        $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';

                    } else {

                        $extension = $allowedTypes[$mimeType];

                        $uploadDirectory = __DIR__ . '/../uploads/products';

                        if (!is_dir($uploadDirectory)) {
                            mkdir($uploadDirectory, 0755, true);
                        }

                        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

                        $destination = $uploadDirectory . '/' . $filename;

                        if (
                            !move_uploaded_file(
                                $_FILES['image']['tmp_name'],
                                $destination
                            )
                        ) {

                            $errors[] = 'Unable to save the product image.';

                        } else {

                            $uploadedImage = 'products/' . $filename;
                        }
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Product
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            try {

                $checkSku = $pdo->prepare("
                    SELECT id
                    FROM products
                    WHERE sku = ?
                    LIMIT 1
                ");

                $checkSku->execute([
                    $sku
                ]);

                if ($checkSku->fetch()) {

                    $errors[] = 'A product with this SKU already exists.';

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO products (
                            name,
                            sku,
                            category,
                            supplier_id,
                            buying_price,
                            selling_price,
                            stock_quantity,
                            reorder_level,
                            image
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $name,
                        $sku,
                        $category !== '' ? $category : null,
                        $supplier_id !== '' ? (int) $supplier_id : null,
                        (float) $buying_price,
                        (float) $selling_price,
                        (int) $stock_quantity,
                        (int) $reorder_level,
                        $uploadedImage
                    ]);

                    header('Location: index.php?added=1');
                    exit;
                }

            } catch (PDOException $e) {

                if ($uploadedImage !== null) {

                    $uploadedPath = __DIR__ . '/../uploads/' . $uploadedImage;

                    if (is_file($uploadedPath)) {
                        unlink($uploadedPath);
                    }
                }

                logError(
                    'Failed to create product.',
                    $e
                );

                $errors[] = 'Unable to create the product. Please try again.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Remove Uploaded Image When Validation Fails
        |--------------------------------------------------------------------------
        */

        if (!empty($errors) && $uploadedImage !== null) {

            $uploadedPath = __DIR__ . '/../uploads/' . $uploadedImage;

            if (is_file($uploadedPath)) {
                unlink($uploadedPath);
            }

            $uploadedImage = null;
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

    <title>Add Product | SoftgeniousDev Inventory</title>

    <style>

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --background: #eef3f8;
            --card: #ffffff;
            --danger: #dc2626;
            --success: #16a34a;
        }


        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                radial-gradient(
                    circle at 85% 10%,
                    rgba(59, 130, 246, 0.10),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 15% 80%,
                    rgba(139, 92, 246, 0.08),
                    transparent 30%
                ),
                var(--background);

            color: var(--text);
        }


        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 32px;
            position: relative;
            overflow: hidden;
        }


        .ambient {
            position: fixed;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.25;
            pointer-events: none;
            z-index: 0;
        }


        .ambient-one {
            background: #60a5fa;
            top: -120px;
            right: -80px;
        }


        .ambient-two {
            background: #a78bfa;
            bottom: -120px;
            left: 240px;
        }


        .page {
            max-width: 1100px;
            margin: auto;
            position: relative;
            z-index: 1;
        }


        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }


        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .page-icon {
            width: 52px;
            height: 52px;
            border-radius: 15px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: linear-gradient(
                135deg,
                #2563eb,
                #4f46e5
            );

            color: white;
            font-size: 25px;
            font-weight: 800;

            box-shadow:
                0 10px 25px rgba(37, 99, 235, 0.22);
        }


        h1 {
            margin: 0;
            font-size: 27px;
            letter-spacing: -0.7px;
        }


        .subtitle {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 13px;
        }


        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 11px 16px;

            background: white;
            color: #334155;

            border: 1px solid var(--border);
            border-radius: 10px;

            text-decoration: none;
            font-size: 13px;
            font-weight: 700;

            transition: 0.2s ease;
        }


        .back-button:hover {
            transform: translateY(-2px);
            border-color: #bfdbfe;

            box-shadow:
                0 8px 20px rgba(15, 23, 42, 0.07);
        }


        .form-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 20px;

            box-shadow:
                0 15px 40px rgba(15, 23, 42, 0.07);

            overflow: hidden;
        }


        .card-top {
            padding: 23px 26px;

            border-bottom: 1px solid #edf1f5;

            display: flex;
            align-items: center;
            gap: 12px;
        }


        .card-top-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eff6ff;
            color: var(--primary);

            font-weight: 800;
            font-size: 18px;
        }


        .card-top h2 {
            margin: 0;
            font-size: 16px;
        }


        .card-top p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 12px;
        }


        .form-body {
            padding: 28px;
        }


        .error-box {
            margin-bottom: 25px;
            padding: 15px 17px;

            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;

            color: #991b1b;
        }


        .error-box strong {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
        }


        .error-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 12px;
            line-height: 1.7;
        }


        .section-title {
            margin: 0 0 17px;

            font-size: 14px;
            font-weight: 800;

            color: #334155;
        }


        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
            margin-bottom: 28px;
        }


        .field {
            display: flex;
            flex-direction: column;
        }


        .field.full {
            grid-column: 1 / -1;
        }


        label {
            margin-bottom: 7px;

            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }


        .required {
            color: #dc2626;
        }


        input,
        select {
            width: 100%;
            height: 45px;

            padding: 0 13px;

            background: #fbfcfe;

            border: 1px solid #dbe3ec;
            border-radius: 10px;

            color: #0f172a;

            font-size: 13px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }


        input:focus,
        select:focus {
            background: white;
            border-color: #60a5fa;

            box-shadow:
                0 0 0 4px rgba(37, 99, 235, 0.09);
        }


        input::placeholder {
            color: #94a3b8;
        }


        .field-help {
            margin-top: 6px;
            color: #94a3b8;
            font-size: 11px;
        }


        .image-upload {
            border: 1px dashed #bfdbfe;
            border-radius: 14px;
            padding: 20px;

            background: #f8fbff;
        }


        .image-upload-inner {
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .upload-icon {
            width: 45px;
            height: 45px;
            min-width: 45px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: #eff6ff;
            color: var(--primary);

            font-size: 19px;
            font-weight: 800;
        }


        .upload-text strong {
            display: block;
            font-size: 13px;
            color: #1e293b;
        }


        .upload-text span {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: 11px;
        }


        input[type="file"] {
            height: auto;
            padding: 10px;
            margin-top: 13px;
            background: white;
        }


        .form-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 11px;

            padding-top: 22px;

            border-top: 1px solid #edf1f5;
        }


        .cancel-button,
        .submit-button {
            min-height: 44px;

            padding: 0 20px;

            border-radius: 10px;

            font-size: 13px;
            font-weight: 800;

            text-decoration: none;
            cursor: pointer;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .cancel-button {
            display: inline-flex;
            align-items: center;

            background: white;
            color: #475569;

            border: 1px solid var(--border);
        }


        .submit-button {
            border: none;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            color: white;

            box-shadow:
                0 8px 18px rgba(37, 99, 235, 0.20);
        }


        .cancel-button:hover,
        .submit-button:hover {
            transform: translateY(-2px);
        }


        .submit-button:hover {
            box-shadow:
                0 12px 24px rgba(37, 99, 235, 0.28);
        }


        @media (max-width: 900px) {

            .main-content {
                margin-left: 0;
                padding: 22px 17px;
            }

        }


        @media (max-width: 650px) {

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }


            .header-left {
                width: 100%;
            }


            .back-button {
                width: 100%;
                justify-content: center;
            }


            h1 {
                font-size: 23px;
            }


            .form-grid {
                grid-template-columns: 1fr;
            }


            .field.full {
                grid-column: auto;
            }


            .form-body {
                padding: 20px;
            }


            .card-top {
                padding: 19px 20px;
            }


            .image-upload-inner {
                align-items: flex-start;
            }


            .form-footer {
                flex-direction: column-reverse;
            }


            .cancel-button,
            .submit-button {
                width: 100%;
                justify-content: center;
                display: flex;
                align-items: center;
            }

        }

    </style>

</head>


<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


<div class="ambient ambient-one"></div>
<div class="ambient ambient-two"></div>


<main class="main-content">

    <div class="page">


        <!-- Page Header -->

        <div class="page-header">

            <div class="header-left">

                <div class="page-icon">
                    +
                </div>

                <div>

                    <h1>Add Product</h1>

                    <p class="subtitle">
                        Create a new product and add it to your inventory.
                    </p>

                </div>

            </div>


            <a
                href="index.php"
                class="back-button"
            >
                ← Back to Products
            </a>

        </div>


        <!-- Form Card -->

        <div class="form-card">


            <div class="card-top">

                <div class="card-top-icon">
                    +
                </div>

                <div>

                    <h2>Product Information</h2>

                    <p>
                        Enter the product details below.
                    </p>

                </div>

            </div>


            <div class="form-body">


                <?php if (!empty($errors)): ?>

                    <div class="error-box">

                        <strong>
                            Please correct the following:
                        </strong>

                        <ul>

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token()) ?>"
                    >


                    <!-- Basic Information -->

                    <h3 class="section-title">
                        Basic Information
                    </h3>


                    <div class="form-grid">


                        <div class="field full">

                            <label for="name">
                                Product Name
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?= htmlspecialchars($name) ?>"
                                placeholder="e.g. HP EliteBook 840 G8"
                                required
                            >

                        </div>


                        <div class="field">

                            <label for="sku">
                                SKU
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="sku"
                                name="sku"
                                value="<?= htmlspecialchars($sku) ?>"
                                placeholder="e.g. HP-840-G8"
                                required
                            >

                            <span class="field-help">
                                A unique product identification code.
                            </span>

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
                                placeholder="e.g. Laptops"
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
                                    — No supplier —
                                </option>

                                <?php foreach ($suppliers as $supplier): ?>

                                    <option
                                        value="<?= (int) $supplier['id'] ?>"
                                        <?= (string) $supplier_id === (string) $supplier['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- Pricing & Stock -->

                    <h3 class="section-title">
                        Pricing & Stock
                    </h3>


                    <div class="form-grid">


                        <div class="field">

                            <label for="buying_price">
                                Buying Price
                                <span class="required">*</span>
                            </label>

                            <input
                                type="number"
                                id="buying_price"
                                name="buying_price"
                                value="<?= htmlspecialchars($buying_price) ?>"
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                required
                            >

                        </div>


                        <div class="field">

                            <label for="selling_price">
                                Selling Price
                                <span class="required">*</span>
                            </label>

                            <input
                                type="number"
                                id="selling_price"
                                name="selling_price"
                                value="<?= htmlspecialchars($selling_price) ?>"
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                required
                            >

                        </div>


                        <div class="field">

                            <label for="stock_quantity">
                                Initial Stock
                                <span class="required">*</span>
                            </label>

                            <input
                                type="number"
                                id="stock_quantity"
                                name="stock_quantity"
                                value="<?= htmlspecialchars($stock_quantity) ?>"
                                placeholder="0"
                                min="0"
                                step="1"
                                required
                            >

                        </div>


                        <div class="field">

                            <label for="reorder_level">
                                Reorder Level
                                <span class="required">*</span>
                            </label>

                            <input
                                type="number"
                                id="reorder_level"
                                name="reorder_level"
                                value="<?= htmlspecialchars($reorder_level) ?>"
                                placeholder="5"
                                min="0"
                                step="1"
                                required
                            >

                            <span class="field-help">
                                You'll receive a low-stock alert at this level.
                            </span>

                        </div>

                    </div>


                    <!-- Product Image -->

                    <h3 class="section-title">
                        Product Image
                    </h3>


                    <div class="image-upload">

                        <div class="image-upload-inner">

                            <div class="upload-icon">
                                ↑
                            </div>

                            <div class="upload-text">

                                <strong>
                                    Upload Product Image
                                </strong>

                                <span>
                                    JPG, PNG or WEBP · Maximum 2MB
                                </span>

                            </div>

                        </div>


                        <input
                            type="file"
                            id="image"
                            name="image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                    </div>


                    <!-- Actions -->

                    <div class="form-footer">

                        <a
                            href="index.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>


                        <button
                            type="submit"
                            class="submit-button"
                        >
                            Save Product
                        </button>

                    </div>


                </form>

            </div>

        </div>

    </div>

</main>

</body>

</html>
