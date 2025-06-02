<?php

namespace OnaOnbir\OOAutoWeave\Core\Support;

use Illuminate\Support\Facades\Log;

class Logger
{
    protected static string $defaultPrefix = 'OOAutoWeave';

    public static function info(string $message, array $context = [], ?string $source = null): void
    {
        if (config('oo-auto-weave.debug_logs')) {
            Log::info(self::formatMessage($message, $source), $context);
        }
    }

    public static function warning(string $message, array $context = [], ?string $source = null): void
    {
        if (config('oo-auto-weave.debug_logs')) {
            Log::warning(self::formatMessage($message, $source), $context);
        }
    }

    public static function error(string $message, array $context = [], ?string $source = null): void
    {
        if (config('oo-auto-weave.debug_logs')) {
            Log::error(self::formatMessage($message, $source), $context);
        }
    }

    protected static function formatMessage(string $message, ?string $source = null): string
    {
        $prefix = '[' . self::$defaultPrefix . ']';
        if ($source) {
            $prefix .= ' - [' . strtoupper($source) . ']';
        }
        return "{$prefix} {$message}";
    }
}
