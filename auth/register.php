<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$errors = [];

$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $errors[] = 'Name is required.';

    } elseif (strlen($name) > 100) {

        $errors[] = 'Name must not exceed 100 characters.';
    }


    if (
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $errors[] = 'A valid email address is required.';

    } elseif (strlen($email) > 150) {

        $errors[] = 'Email address is too long.';
    }


    if (strlen($password) < 8) {

        $errors[] =
            'Password must be at least 8 characters.';
    }


    if ($password !== $confirmPassword) {

        $errors[] =
            'Passwords do not match.';
    }


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $errors[] =
                'An account with this email already exists.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Account
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO users (
                name,
                email,
                password,
                role
            )
            VALUES (?, ?, ?, 'user')
        ");

        $stmt->execute([
            $name,
            $email,
            $hashedPassword
        ]);

        header('Location: login.php?registered=1');

        exit;
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

    <title>Create Account | SoftgeniousDev</title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #eef3f8 0%,
                    #f8fafc 50%,
                    #eef2ff 100%
                );

            color: #172033;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 18px;
            overflow-x: hidden;
            position: relative;
        }


        /* Ambient background */

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.22;
            pointer-events: none;
        }

        body::before {
            background: #8b5cf6;
            top: -150px;
            right: -120px;
        }

        body::after {
            background: #3b82f6;
            bottom: -170px;
            left: -120px;
        }


        .page {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }


        /* Card */

        .card {
            width: 100%;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 22px;
            padding: 38px;
            box-shadow:
                0 24px 60px rgba(15, 23, 42, 0.10),
                0 4px 14px rgba(15, 23, 42, 0.04);

            animation:
                cardEnter 0.55s ease both;
        }


        @keyframes cardEnter {

            from {
                opacity: 0;
                transform: translateY(18px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /* Brand */

        .brand {
            text-align: center;
            margin-bottom: 30px;
        }


        .brand-mark {
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 16px;

            background:
                linear-gradient(
                    135deg,
                    #172033,
                    #334155
                );

            color: #ffffff;

            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;

            box-shadow:
                0 12px 25px rgba(23, 32, 51, 0.18);

            animation: logoFloat 3s ease-in-out infinite;
        }


        @keyframes logoFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-3px);
            }
        }


        .brand h1 {
            margin: 0 0 8px;
            font-size: 27px;
            line-height: 1.2;
            letter-spacing: -0.6px;
        }


        .brand p {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.5;
        }


        /* Error */

        .error-box {
            margin-bottom: 22px;
            padding: 14px 16px;

            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 12px;

            color: #b42318;
            font-size: 13px;
            line-height: 1.55;
        }


        .error-title {
            margin-bottom: 6px;
            font-weight: 700;
        }


        .error-box ul {
            margin: 0;
            padding-left: 20px;
        }


        .error-box li + li {
            margin-top: 4px;
        }


        /* Form */

        .field {
            margin-bottom: 19px;
        }


        label {
            display: block;
            margin-bottom: 8px;

            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }


        .input-wrap {
            position: relative;
        }


        input {
            width: 100%;
            height: 48px;

            padding: 0 14px;

            border: 1px solid #d0d5dd;
            border-radius: 11px;

            background: #ffffff;

            color: #172033;

            font-family: inherit;
            font-size: 14px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }


        input::placeholder {
            color: #98a2b3;
        }


        input:hover {
            border-color: #b8c0cc;
        }


        input:focus {
            outline: none;
            border-color: #4f46e5;

            box-shadow:
                0 0 0 4px rgba(79, 70, 229, 0.10);

            background: #ffffff;
        }


        /* Password section */

        .password-note {
            margin-top: 7px;
            color: #98a2b3;
            font-size: 12px;
        }


        /* Button */

        .btn {
            width: 100%;
            height: 49px;

            margin-top: 4px;

            border: none;
            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            color: #ffffff;

            font-family: inherit;
            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 10px 22px rgba(37, 99, 235, 0.20);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                filter 0.2s ease;
        }


        .btn:hover {
            transform: translateY(-1px);

            box-shadow:
                0 14px 28px rgba(37, 99, 235, 0.25);

            filter: brightness(1.03);
        }


        .btn:active {
            transform: translateY(0);
        }


        /* Login */

        .login-link {
            text-align: center;
            margin-top: 24px;

            color: #667085;
            font-size: 13px;
        }


        .login-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 700;
        }


        .login-link a:hover {
            text-decoration: underline;
        }


        /* Security note */

        .security-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            margin-top: 24px;
            padding-top: 20px;

            border-top: 1px solid #eef2f6;

            color: #98a2b3;
            font-size: 11px;
        }


        .security-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;

            box-shadow:
                0 0 0 4px rgba(34, 197, 94, 0.10);
        }


        /* Mobile */

        @media (max-width: 520px) {

            body {
                padding: 18px 12px;
            }

            .card {
                padding: 28px 22px;
                border-radius: 18px;
            }

            .brand {
                margin-bottom: 25px;
            }

            .brand h1 {
                font-size: 24px;
            }

        }

    </style>

</head>


<body>

<div class="page">

    <div class="card">

        <div class="brand">

            <div class="brand-mark">
                SG
            </div>

            <h1>
                Create Account
            </h1>

            <p>
                Set up your SoftgeniousDev inventory account
            </p>

        </div>


        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <div class="error-title">
                    Please check the following:
                </div>

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <!-- CSRF Protection -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    csrf_token(),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <!-- Full Name -->

            <div class="field">

                <label for="name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars(
                        $name,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="Enter your full name"
                    maxlength="100"
                    autocomplete="name"
                    required
                >

            </div>


            <!-- Email -->

            <div class="field">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars(
                        $email,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="you@example.com"
                    maxlength="150"
                    autocomplete="email"
                    required
                >

            </div>


            <!-- Password -->

            <div class="field">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    placeholder="Create a password"
                    required
                >

                <div class="password-note">
                    Minimum 8 characters.
                </div>

            </div>


            <!-- Confirm Password -->

            <div class="field">

                <label for="confirm_password">
                    Confirm Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    placeholder="Repeat your password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                Create Account
            </button>

        </form>


        <div class="login-link">

            Already have an account?

            <a href="login.php">
                Login
            </a>

        </div>


        <div class="security-note">

            <span class="security-dot"></span>

            Secure account registration

        </div>

    </div>

</div>

</body>

</html>