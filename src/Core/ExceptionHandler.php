<?php

namespace App\Core;

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handleException(\Throwable $e): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $type = get_class($e);
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();

        $logEntry = "[{$timestamp}] {$type}: {$message} | {$file}:{$line}" . PHP_EOL;
        $logPath = __DIR__ . '/../../storage/logs/app.log';
        file_put_contents($logPath, $logEntry, FILE_APPEND);

        http_response_code(500);
        require __DIR__ . '/../Views/errors/500.php';
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
}
