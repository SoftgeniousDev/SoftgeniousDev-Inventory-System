<?php

require_once __DIR__ . '/logger.php';


/*
|--------------------------------------------------------------------------
| Convert PHP Errors Into Exceptions
|--------------------------------------------------------------------------
*/

set_error_handler(function (
    int $severity,
    string $message,
    string $file,
    int $line
): bool {

    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException(
        $message,
        0,
        $severity,
        $file,
        $line
    );
});


/*
|--------------------------------------------------------------------------
| Handle Uncaught Exceptions
|--------------------------------------------------------------------------
*/

set_exception_handler(function (Throwable $exception): void {

    logError(
        'Unhandled application exception.',
        $exception
    );

    http_response_code(500);

    echo 'A system error occurred. Please try again later.';
});


/*
|--------------------------------------------------------------------------
| Handle Fatal Errors
|--------------------------------------------------------------------------
*/

register_shutdown_function(function (): void {

    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR
    ];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    $exception = new ErrorException(
        $error['message'],
        0,
        $error['type'],
        $error['file'],
        $error['line']
    );

    logError(
        'Fatal PHP error.',
        $exception
    );

    http_response_code(500);

    echo 'A system error occurred. Please try again later.';
});