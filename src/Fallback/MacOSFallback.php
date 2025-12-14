<?php

declare(strict_types=1);

namespace SoloTerm\Notify\Fallback;

/**
 * macOS notification fallback using osascript (AppleScript).
 */
class MacOSFallback implements FallbackInterface
{
    /**
     * Cached availability check result.
     */
    protected static ?bool $available = null;

    /**
     * Check if osascript is available.
     */
    public function isAvailable(): bool
    {
        if (static::$available !== null) {
            return static::$available;
        }

        // Must be macOS
        if (PHP_OS_FAMILY !== 'Darwin') {
            static::$available = false;

            return false;
        }

        // Check for osascript command
        static::$available = $this->commandExists('osascript');

        return static::$available;
    }

    /**
     * Send notification via osascript.
     */
    public function send(string $message, ?string $title = null, ?int $urgency = null): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $title = $this->escapeAppleScript($title ?? 'Notification');
        $message = $this->escapeAppleScript($message);

        $script = sprintf(
            'display notification "%s" with title "%s"',
            $message,
            $title
        );

        $command = sprintf('osascript -e %s 2>/dev/null', escapeshellarg($script));

        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Escape a string for use in AppleScript.
     */
    protected function escapeAppleScript(string $str): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $str);
    }

    /**
     * Check if a command exists in the system PATH.
     */
    protected function commandExists(string $command): bool
    {
        $result = shell_exec("which {$command} 2>/dev/null");

        return ! empty(trim($result ?? ''));
    }

    /**
     * Reset the cached availability check (for testing).
     */
    public static function reset(): void
    {
        static::$available = null;
    }
}
