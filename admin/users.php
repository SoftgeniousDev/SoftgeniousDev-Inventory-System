<?php

require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$errors = [];
$success = '';

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';

    } else {

        $action = $_POST['action'] ?? '';
        $userId = (int) ($_POST['user_id'] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | Change Role
        |--------------------------------------------------------------------------
        */

        if ($action === 'change_role') {

            $newRole = $_POST['role'] ?? '';

            if ($userId <= 0) {

                $errors[] = 'Invalid user selected.';

            } elseif (!in_array($newRole, ['user', 'admin'], true)) {

                $errors[] = 'Invalid role selected.';

            } elseif ($userId === $currentUserId) {

                $errors[] =
                    'You cannot change your own administrator role here.';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET role = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $newRole,
                        $userId
                    ]);

                    if ($stmt->rowCount() > 0) {

                        $success =
                            'User role updated successfully.';

                    } else {

                        $errors[] =
                            'No changes were made.';
                    }

                } catch (PDOException $e) {

                    if (function_exists('logError')) {

                        logError(
                            'Failed to update user role.',
                            $e
                        );
                    }

                    $errors[] =
                        'The user role could not be updated.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Delete User
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_user') {

            if ($userId <= 0) {

                $errors[] =
                    'Invalid user selected.';

            } elseif ($userId === $currentUserId) {

                $errors[] =
                    'You cannot delete your own account.';

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Make sure the user exists
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT id, name
                        FROM users
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        $userId
                    ]);

                    $userToDelete = $stmt->fetch();

                    if (!$userToDelete) {

                        $errors[] =
                            'The selected user no longer exists.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Delete API tokens first
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare("
                            DELETE FROM api_tokens
                            WHERE user_id = ?
                        ");

                        $stmt->execute([
                            $userId
                        ]);


                        /*
                        |--------------------------------------------------------------------------
                        | Delete User
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare("
                            DELETE FROM users
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $userId
                        ]);

                        if ($stmt->rowCount() > 0) {

                            $success =
                                'User "' .
                                htmlspecialchars(
                                    $userToDelete['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) .
                                '" was deleted successfully.';

                        } else {

                            $errors[] =
                                'The user could not be deleted.';
                        }
                    }

                } catch (PDOException $e) {

                    if (function_exists('logError')) {

                        logError(
                            'Failed to delete user.',
                            $e
                        );
                    }

                    $errors[] =
                        'The user could not be deleted. They may have related records.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');


/*
|--------------------------------------------------------------------------
| Load Users
|--------------------------------------------------------------------------
*/

try {

    if ($search !== '') {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                email,
                role,
                created_at
            FROM users
            WHERE
                name LIKE ?
                OR email LIKE ?
            ORDER BY created_at DESC
        ");

        $term = '%' . $search . '%';

        $stmt->execute([
            $term,
            $term
        ]);

    } else {

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                email,
                role,
                created_at
            FROM users
            ORDER BY created_at DESC
        ");
    }

    $users = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    $totalUsers = (int) $pdo
        ->query("SELECT COUNT(*) FROM users")
        ->fetchColumn();

    $totalAdmins = (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'admin'
        ")
        ->fetchColumn();

    $totalRegularUsers = (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'user'
        ")
        ->fetchColumn();

} catch (PDOException $e) {

    if (function_exists('logError')) {

        logError(
            'Failed to load admin user management page.',
            $e
        );
    }

    $users = [];

    $totalUsers = 0;
    $totalAdmins = 0;
    $totalRegularUsers = 0;

    $errors[] =
        'User information could not be loaded.';
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

    <title>Manage Users | SoftgeniousDev</title>

    <style>

        * {
            box-sizing: border-box;
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
        }


        body::before,
        body::after {
            content: "";

            position: fixed;

            width: 360px;
            height: 360px;

            border-radius: 50%;

            filter: blur(80px);

            opacity: 0.18;

            pointer-events: none;

            z-index: 0;
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


        .main {
            margin-left: 260px;

            min-height: 100vh;

            padding: 40px;

            position: relative;

            z-index: 1;
        }


        .page {
            max-width: 1180px;

            margin: 0 auto;
        }


        /* Header */

        .page-header {
            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 28px;
        }


        .eyebrow {
            margin: 0 0 7px;

            color: #4f46e5;

            font-size: 12px;

            font-weight: 800;

            letter-spacing: 0.08em;

            text-transform: uppercase;
        }


        h1 {
            margin: 0;

            font-size: 30px;

            letter-spacing: -0.7px;
        }


        .subtitle {
            margin: 8px 0 0;

            color: #667085;

            font-size: 14px;
        }


        .add-button {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 44px;

            padding: 0 17px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            color: #ffffff;

            text-decoration: none;

            font-size: 13px;

            font-weight: 700;

            box-shadow:
                0 9px 20px rgba(37, 99, 235, 0.18);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .add-button:hover {
            transform: translateY(-1px);

            box-shadow:
                0 13px 25px rgba(37, 99, 235, 0.23);
        }


        /* Stats */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 16px;

            margin-bottom: 22px;
        }


        .stat-card {
            background: rgba(255, 255, 255, 0.96);

            border: 1px solid #e5eaf0;

            border-radius: 15px;

            padding: 20px;

            box-shadow:
                0 10px 25px rgba(15, 23, 42, 0.05);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .stat-card:hover {
            transform: translateY(-2px);

            box-shadow:
                0 15px 30px rgba(15, 23, 42, 0.07);
        }


        .stat-label {
            color: #667085;

            font-size: 12px;

            font-weight: 600;
        }


        .stat-value {
            margin-top: 7px;

            color: #172033;

            font-size: 25px;

            font-weight: 800;

            letter-spacing: -0.5px;
        }


        .stat-note {
            margin-top: 4px;

            color: #98a2b3;

            font-size: 11px;
        }


        /* Alerts */

        .success,
        .error-box {
            margin-bottom: 20px;

            padding: 13px 15px;

            border-radius: 11px;

            font-size: 13px;
        }


        .success {
            background: #f0fdf4;

            border: 1px solid #bbf7d0;

            color: #166534;

            font-weight: 600;
        }


        .error-box {
            background: #fff5f5;

            border: 1px solid #fecaca;

            color: #b42318;

            line-height: 1.55;
        }


        .error-box ul {
            margin: 0;

            padding-left: 20px;
        }


        /* Main card */

        .card {
            background: rgba(255, 255, 255, 0.97);

            border: 1px solid #e5eaf0;

            border-radius: 18px;

            box-shadow:
                0 15px 35px rgba(15, 23, 42, 0.06);

            overflow: hidden;
        }


        .card-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 18px;

            padding: 22px 24px;

            border-bottom: 1px solid #eef2f6;
        }


        .card-title {
            margin: 0;

            font-size: 17px;
        }


        .card-description {
            margin: 4px 0 0;

            color: #98a2b3;

            font-size: 12px;
        }


        /* Search */

        .search-form {
            display: flex;

            gap: 8px;

            width: min(360px, 100%);
        }


        .search-input {
            flex: 1;

            height: 40px;

            padding: 0 12px;

            border: 1px solid #d0d5dd;

            border-radius: 9px;

            font-family: inherit;

            font-size: 13px;

            color: #172033;

            background: #ffffff;
        }


        .search-input:focus {
            outline: none;

            border-color: #4f46e5;

            box-shadow:
                0 0 0 3px rgba(79, 70, 229, 0.08);
        }


        .search-button {
            height: 40px;

            padding: 0 14px;

            border: 1px solid #dbe2ea;

            border-radius: 9px;

            background: #f8fafc;

            color: #344054;

            font-family: inherit;

            font-size: 12px;

            font-weight: 700;

            cursor: pointer;
        }


        .search-button:hover {
            background: #f1f5f9;
        }


        /* Table */

        .table-wrap {
            width: 100%;

            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 760px;
        }


        th {
            padding: 13px 20px;

            text-align: left;

            background: #f8fafc;

            color: #667085;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 0.05em;

            text-transform: uppercase;

            border-bottom: 1px solid #e5eaf0;
        }


        td {
            padding: 15px 20px;

            border-bottom: 1px solid #eef2f6;

            color: #344054;

            font-size: 13px;

            vertical-align: middle;
        }


        tbody tr {
            transition: background 0.15s ease;
        }


        tbody tr:hover {
            background: #fafbfc;
        }


        tbody tr:last-child td {
            border-bottom: none;
        }


        .user-name {
            color: #172033;

            font-weight: 700;
        }


        .user-email {
            color: #667085;

            font-size: 12px;
        }


        .current-user {
            margin-left: 6px;

            padding: 3px 7px;

            border-radius: 999px;

            background: #eef2ff;

            color: #4338ca;

            font-size: 9px;

            font-weight: 800;

            text-transform: uppercase;
        }


        /* Role */

        .role-badge {
            display: inline-flex;

            align-items: center;

            padding: 5px 9px;

            border-radius: 999px;

            font-size: 10px;

            font-weight: 800;
        }


        .role-admin {
            background: #f3e8ff;

            color: #7e22ce;
        }


        .role-user {
            background: #eff6ff;

            color: #1d4ed8;
        }


        /* Role form */

        .role-form {
            display: flex;

            align-items: center;

            gap: 7px;
        }


        .role-select {
            height: 34px;

            padding: 0 8px;

            border: 1px solid #dbe2ea;

            border-radius: 8px;

            background: #ffffff;

            color: #344054;

            font-family: inherit;

            font-size: 11px;

            cursor: pointer;
        }


        .role-save {
            height: 34px;

            padding: 0 9px;

            border: none;

            border-radius: 8px;

            background: #eef2ff;

            color: #4338ca;

            font-family: inherit;

            font-size: 10px;

            font-weight: 800;

            cursor: pointer;
        }


        .role-save:hover {
            background: #e0e7ff;
        }


        /* Delete */

        .delete-button {
            height: 34px;

            padding: 0 10px;

            border: 1px solid #fecaca;

            border-radius: 8px;

            background: #fffafa;

            color: #b42318;

            font-family: inherit;

            font-size: 10px;

            font-weight: 800;

            cursor: pointer;
        }


        .delete-button:hover {
            background: #fff1f2;
        }


        .protected {
            color: #98a2b3;

            font-size: 11px;

            font-weight: 600;
        }


        /* Empty */

        .empty {
            padding: 55px 20px;

            text-align: center;

            color: #98a2b3;
        }


        .empty strong {
            display: block;

            margin-bottom: 5px;

            color: #475467;

            font-size: 14px;
        }


        /* Responsive */

        @media (max-width: 1000px) {

            .main {
                margin-left: 0;

                padding: 28px 22px;
            }

        }


        @media (max-width: 760px) {

            .stats {
                grid-template-columns: 1fr;
            }


            .page-header {
                flex-direction: column;
            }


            .add-button {
                width: 100%;
            }


            .card-header {
                align-items: stretch;

                flex-direction: column;
            }


            .search-form {
                width: 100%;
            }

        }


        @media (max-width: 520px) {

            .main {
                padding: 22px 14px;
            }


            h1 {
                font-size: 25px;
            }


            .card-header {
                padding: 18px;
            }

        }

    </style>

</head>


<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


<main class="main">

    <div class="page">


        <!-- Header -->

        <div class="page-header">

            <div>

                <p class="eyebrow">
                    Administration
                </p>

                <h1>
                    Manage Users
                </h1>

                <p class="subtitle">
                    View accounts and manage system access.
                </p>

            </div>


            <a
                href="add_user.php"
                class="add-button"
            >
                + Add User
            </a>

        </div>


        <!-- Messages -->

        <?php if ($success !== ''): ?>

            <div class="success">
                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($errors)): ?>

            <div class="error-box">

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


        <!-- Statistics -->

        <div class="stats">

            <div class="stat-card">

                <div class="stat-label">
                    Total Users
                </div>

                <div class="stat-value">
                    <?= number_format($totalUsers) ?>
                </div>

                <div class="stat-note">
                    Registered accounts
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Administrators
                </div>

                <div class="stat-value">
                    <?= number_format($totalAdmins) ?>
                </div>

                <div class="stat-note">
                    Elevated access
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Regular Users
                </div>

                <div class="stat-value">
                    <?= number_format($totalRegularUsers) ?>
                </div>

                <div class="stat-note">
                    Standard access
                </div>

            </div>

        </div>


        <!-- Users -->

        <section class="card">

            <div class="card-header">

                <div>

                    <h2 class="card-title">
                        User Accounts
                    </h2>

                    <p class="card-description">
                        <?= $search !== ''
                            ? 'Search results for "' .
                              htmlspecialchars(
                                  $search,
                                  ENT_QUOTES,
                                  'UTF-8'
                              ) .
                              '"'
                            : 'All registered system accounts' ?>
                    </p>

                </div>


                <form
                    method="GET"
                    class="search-form"
                >

                    <input
                        type="search"
                        name="search"
                        class="search-input"
                        placeholder="Search name or email..."
                        value="<?= htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="search-button"
                    >
                        Search
                    </button>

                </form>

            </div>


            <?php if (empty($users)): ?>

                <div class="empty">

                    <strong>
                        No users found
                    </strong>

                    <?php if ($search !== ''): ?>

                        Try a different name or email.

                    <?php else: ?>

                        No accounts have been created yet.

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    User
                                </th>

                                <th>
                                    Role
                                </th>

                                <th>
                                    Change Role
                                </th>

                                <th>
                                    Registered
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($users as $user): ?>

                            <?php
                                $userId = (int) $user['id'];

                                $isCurrentUser =
                                    $userId === $currentUserId;
                            ?>

                            <tr>


                                <!-- User -->

                                <td>

                                    <div class="user-name">

                                        <?= htmlspecialchars(
                                            $user['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                        <?php if ($isCurrentUser): ?>

                                            <span class="current-user">
                                                You
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <div class="user-email">

                                        <?= htmlspecialchars(
                                            $user['email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </div>

                                </td>


                                <!-- Role -->

                                <td>

                                    <span
                                        class="
                                            role-badge
                                            <?= $user['role'] === 'admin'
                                                ? 'role-admin'
                                                : 'role-user' ?>
                                        "
                                    >

                                        <?= $user['role'] === 'admin'
                                            ? 'Administrator'
                                            : 'User' ?>

                                    </span>

                                </td>


                                <!-- Change Role -->

                                <td>

                                    <?php if ($isCurrentUser): ?>

                                        <span class="protected">
                                            Protected
                                        </span>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            class="role-form"
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

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="change_role"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $userId ?>"
                                            >


                                            <select
                                                name="role"
                                                class="role-select"
                                            >

                                                <option
                                                    value="user"
                                                    <?= $user['role'] === 'user'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    User
                                                </option>

                                                <option
                                                    value="admin"
                                                    <?= $user['role'] === 'admin'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    Administrator
                                                </option>

                                            </select>


                                            <button
                                                type="submit"
                                                class="role-save"
                                            >
                                                Save
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </td>


                                <!-- Registered -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'M d, Y',
                                            strtotime(
                                                $user['created_at']
                                            )
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <!-- Delete -->

                                <td>

                                    <?php if ($isCurrentUser): ?>

                                        <span class="protected">
                                            Protected
                                        </span>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Are you sure you want to delete this user account? This action cannot be undone.'
                                                );
                                            "
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

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete_user"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $userId ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="delete-button"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

</body>

</html>