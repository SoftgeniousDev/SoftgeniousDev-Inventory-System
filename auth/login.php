<?php

session_start();

require_once '../config/database.php';
require_once '../includes/csrf.php';

$errors = [];

$email = '';

$registered = isset($_GET['registered']);


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


    /*
    |--------------------------------------------------------------------------
    | Get Login Data
    |--------------------------------------------------------------------------
    */

    $email = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($email === '') {

        $errors[] =
            'Email address is required.';
    }


    if ($password === '') {

        $errors[] =
            'Password is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Authenticate User
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                email,
                password,
                role
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $email
        ]);

        $user = $stmt->fetch();


        if (
            !$user ||
            !password_verify(
                $password,
                $user['password']
            )
        ) {

            $errors[] =
                'Invalid email or password.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Prevent Session Fixation
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);


            /*
            |--------------------------------------------------------------------------
            | Store User Session
            |--------------------------------------------------------------------------
            */

            $_SESSION['user_id'] =
                (int) $user['id'];

            $_SESSION['user_name'] =
                $user['name'];

            $_SESSION['user_email'] =
                $user['email'];

            $_SESSION['user_role'] =
                $user['role'];


            /*
            |--------------------------------------------------------------------------
            | Redirect To Dashboard
            |--------------------------------------------------------------------------
            */

            header(
                'Location: ../index.php'
            );

            exit;
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

    <title>Login | SoftgeniousDev</title>

    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #172033;
        }


        .card {
            width: 100%;
            max-width: 450px;
            margin: 20px;
            padding: 30px;
            background: white;
            border: 1px solid #e4e7ec;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(16, 24, 40, 0.05);
        }


        .brand {
            text-align: center;
            margin-bottom: 25px;
        }


        .brand-mark {
            width: 50px;
            height: 50px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #172033;
            color: white;
            border-radius: 12px;
            font-weight: bold;
            font-size: 20px;
        }


        .brand h1 {
            margin: 0 0 6px;
        }


        .brand p {
            margin: 0;
            color: #667085;
            font-size: 14px;
        }


        .success-box {
            background: #ecfdf3;
            border: 1px solid #abefc6;
            color: #067647;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }


        .error-box {
            background: #fef3f2;
            border: 1px solid #fecdca;
            color: #b42318;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }


        .error-box ul {
            margin: 0;
            padding-left: 20px;
        }


        .field {
            margin-bottom: 18px;
        }


        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 600;
        }


        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            font-size: 14px;
        }


        input:focus {
            outline: none;
            border-color: #1570ef;
        }


        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #1570ef;
            color: white;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
        }


        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #667085;
        }


        .register-link a {
            color: #1570ef;
            text-decoration: none;
            font-weight: 600;
        }

    </style>

</head>


<body>


<div class="card">


    <div class="brand">

        <div class="brand-mark">
            SG
        </div>

        <h1>Welcome Back</h1>

        <p>
            SoftgeniousDev Inventory Management
        </p>

    </div>


    <?php if ($registered): ?>

        <div class="success-box">

            Account created successfully.
            You can now log in.

        </div>

    <?php endif; ?>


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


        <!-- CSRF Protection -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrf_token()) ?>"
        >


        <div class="field">

            <label for="email">
                Email Address
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email) ?>"
                required
            >

        </div>


        <div class="field">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                required
            >

        </div>


        <button
            type="submit"
            class="btn"
        >
            Login
        </button>


    </form>


    <div class="register-link">

        Don't have an account?

        <a href="register.php">
            Create Account
        </a>

    </div>


</div>


</body>

</html>
