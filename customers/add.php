<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$errors = [];

$name = '';
$phone = '';
$email = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        $errors[] = 'Customer name is required.';
    }

    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO customers (
                    name,
                    phone,
                    email,
                    address
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null
            ]);

            header('Location: index.php?added=1');
            exit;

        } catch (PDOException $e) {

            if (function_exists('logError')) {
                logError(
                    'Failed to create customer.',
                    $e
                );
            }

            $errors[] =
                'Unable to save the customer. Please try again later.';
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

    <title>Add Customer | SoftgeniousDev Inventory</title>

    <style>

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --background: #eef3f8;
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

            filter: blur(75px);

            opacity: 0.22;

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
            max-width: 1050px;
            margin: 0 auto;

            position: relative;
            z-index: 1;
        }


        /* PAGE HEADER */

        .page-header {
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


        .page-icon {
            width: 52px;
            height: 52px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 15px;

            background:
                linear-gradient(
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

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }


        .back-button:hover {
            transform: translateY(-2px);

            border-color: #bfdbfe;

            box-shadow:
                0 8px 20px rgba(15, 23, 42, 0.07);
        }


        /* FORM CARD */

        .form-card {
            background: rgba(255, 255, 255, 0.96);

            border: 1px solid rgba(226, 232, 240, 0.9);

            border-radius: 20px;

            box-shadow:
                0 15px 40px rgba(15, 23, 42, 0.07);

            overflow: hidden;
        }


        .card-top {
            display: flex;
            align-items: center;

            gap: 12px;

            padding: 23px 26px;

            border-bottom: 1px solid #edf1f5;
        }


        .card-top-icon {
            width: 39px;
            height: 39px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 10px;

            background: #eff6ff;

            color: var(--primary);

            font-size: 20px;
            font-weight: 800;
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


        /* ERRORS */

        .error-box {
            margin-bottom: 25px;

            padding: 15px 17px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            border-radius: 12px;

            color: #991b1b;
        }


        .error-title {
            display: block;

            margin-bottom: 7px;

            font-size: 13px;
            font-weight: 800;
        }


        .error-box ul {
            margin: 0;

            padding-left: 20px;

            font-size: 12px;

            line-height: 1.7;
        }


        /* SECTION */

        .section-title {
            margin: 0 0 17px;

            color: #334155;

            font-size: 14px;
            font-weight: 800;
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
        textarea {
            width: 100%;

            border: 1px solid #dbe3ec;

            border-radius: 10px;

            background: #fbfcfe;

            color: #0f172a;

            font-family: inherit;

            font-size: 13px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }


        input {
            height: 45px;

            padding: 0 13px;
        }


        textarea {
            min-height: 120px;

            padding: 13px;

            resize: vertical;

            line-height: 1.5;
        }


        input::placeholder,
        textarea::placeholder {
            color: #94a3b8;
        }


        input:focus,
        textarea:focus {
            background: white;

            border-color: #60a5fa;

            box-shadow:
                0 0 0 4px rgba(37, 99, 235, 0.09);
        }


        .field-help {
            margin-top: 6px;

            color: #94a3b8;

            font-size: 11px;
        }


        /* CUSTOMER INFO PANEL */

        .info-panel {
            display: flex;
            align-items: center;

            gap: 15px;

            margin-bottom: 28px;

            padding: 17px;

            border: 1px solid #dbeafe;

            border-radius: 14px;

            background:
                linear-gradient(
                    135deg,
                    #f8fbff,
                    #f5f7ff
                );
        }


        .info-icon {
            width: 44px;
            height: 44px;

            min-width: 44px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: #eff6ff;

            color: var(--primary);

            font-size: 20px;
            font-weight: 800;
        }


        .info-panel strong {
            display: block;

            color: #1e293b;

            font-size: 13px;
        }


        .info-panel span {
            display: block;

            margin-top: 3px;

            color: #64748b;

            font-size: 11px;

            line-height: 1.5;
        }


        /* FOOTER */

        .form-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;

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


        /* RESPONSIVE */

        @media (max-width: 900px) {

            .main-content {
                margin-left: 0;

                padding: 22px 17px;
            }

        }


        @media (max-width: 650px) {

            .page-header {
                flex-direction: column;

                align-items: flex-start;
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


            .form-footer {
                flex-direction: column-reverse;
            }


            .cancel-button,
            .submit-button {
                width: 100%;

                justify-content: center;
            }


            .info-panel {
                align-items: flex-start;
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


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div class="header-left">

                <div class="page-icon">
                    +
                </div>

                <div>

                    <h1>Add Customer</h1>

                    <p class="subtitle">
                        Register a new customer for your sales and inventory system.
                    </p>

                </div>

            </div>


            <a
                href="index.php"
                class="back-button"
            >
                ← Back to Customers
            </a>

        </div>


        <!-- FORM CARD -->

        <div class="form-card">


            <div class="card-top">

                <div class="card-top-icon">
                    +
                </div>

                <div>

                    <h2>Customer Information</h2>

                    <p>
                        Enter the customer's contact details below.
                    </p>

                </div>

            </div>


            <div class="form-body">


                <?php if (!empty($errors)): ?>

                    <div class="error-box">

                        <span class="error-title">
                            Please correct the following:
                        </span>

                        <ul>

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <div class="info-panel">

                    <div class="info-icon">
                        +
                    </div>

                    <div>

                        <strong>
                            Customer profile
                        </strong>

                        <span>
                            Keep customer contact information accurate so sales records
                            and customer history remain easy to manage.
                        </span>

                    </div>

                </div>


                <form method="POST">


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token()) ?>"
                    >


                    <h3 class="section-title">
                        Contact Details
                    </h3>


                    <div class="form-grid">


                        <div class="field full">

                            <label for="name">
                                Customer Name
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?= htmlspecialchars($name) ?>"
                                placeholder="e.g. John Kamau"
                                autocomplete="name"
                                required
                            >

                        </div>


                        <div class="field">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars($phone) ?>"
                                placeholder="e.g. 0712345678"
                                autocomplete="tel"
                            >

                            <span class="field-help">
                                Optional customer contact number.
                            </span>

                        </div>


                        <div class="field">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($email) ?>"
                                placeholder="customer@example.com"
                                autocomplete="email"
                            >

                            <span class="field-help">
                                Optional customer email address.
                            </span>

                        </div>


                        <div class="field full">

                            <label for="address">
                                Address
                            </label>

                            <textarea
                                id="address"
                                name="address"
                                placeholder="Customer physical or postal address"
                                autocomplete="street-address"
                            ><?= htmlspecialchars($address) ?></textarea>

                            <span class="field-help">
                                Optional physical or postal address.
                            </span>

                        </div>

                    </div>


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
                            Save Customer
                        </button>


                    </div>


                </form>

            </div>

        </div>

    </div>

</main>


</body>

</html>
