<?php

function logError(string $message, ?Throwable $exception = null): void
{
    $logDirectory = __DIR__ . '/../logs';

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0755, true);
    }

    $logFile = $logDirectory . '/app.log';

    $timestamp = date('Y-m-d H:i:s');

    $entry = "[{$timestamp}] {$message}";

    if ($exception !== null) {
        $entry .= PHP_EOL;
        $entry .= "Exception: " . get_class($exception) . PHP_EOL;
        $entry .= "Message: " . $exception->getMessage() . PHP_EOL;
        $entry .= "File: " . $exception->getFile() . PHP_EOL;
        $entry .= "Line: " . $exception->getLine() . PHP_EOL;
        $entry .= "Trace: " . $exception->getTraceAsString();
    }

    $entry .= PHP_EOL . str_repeat('-', 80) . PHP_EOL;

    file_put_contents(
        $logFile,
        $entry,
        FILE_APPEND | LOCK_EX
    );
}