<?php

declare(strict_types=1);

namespace SoloTerm\Notify\Fallback;

/**
 * Windows notification fallback using PowerShell toast notifications.
 */
class WindowsFallback implements FallbackInterface
{
    /**
     * Cached availability check result.
     */
    protected static ?bool $available = null;

    /**
     * Check if PowerShell is available on Windows.
     */
    public function isAvailable(): bool
    {
        if (static::$available !== null) {
            return static::$available;
        }

        // Must be Windows
        if (PHP_OS_FAMILY !== 'Windows') {
            static::$available = false;

            return false;
        }

        // PowerShell is available by default on modern Windows
        static::$available = true;

        return static::$available;
    }

    /**
     * Send notification via PowerShell toast.
     */
    public function send(string $message, ?string $title = null, ?int $urgency = null): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $title = $this->escapeForPowerShell($title ?? 'Notification');
        $message = $this->escapeForPowerShell($message);

        // Use Windows balloon notification (works without BurntToast module)
        $script = <<<POWERSHELL
\$ErrorActionPreference = 'SilentlyContinue'
Add-Type -AssemblyName System.Windows.Forms
\$balloon = New-Object System.Windows.Forms.NotifyIcon
\$balloon.Icon = [System.Drawing.SystemIcons]::Information
\$balloon.BalloonTipTitle = "$title"
\$balloon.BalloonTipText = "$message"
\$balloon.Visible = \$true
\$balloon.ShowBalloonTip(5000)
Start-Sleep -Milliseconds 100
\$balloon.Dispose()
POWERSHELL;

        // Encode as base64 to avoid escaping issues
        $encodedScript = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));

        $command = sprintf('powershell -EncodedCommand %s 2>NUL', escapeshellarg($encodedScript));

        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Escape a string for use in PowerShell.
     */
    protected function escapeForPowerShell(string $str): string
    {
        return str_replace(['"', '$', '`'], ['`"', '`$', '``'], $str);
    }

    /**
     * Reset the cached availability check (for testing).
     */
    public static function reset(): void
    {
        static::$available = null;
    }
}
