<?php

require_once __DIR__ . '/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}