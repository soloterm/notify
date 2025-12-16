<?php

declare(strict_types=1);

namespace SoloTerm\Notify\Fallback;

/**
 * Linux notification fallback using notify-send (libnotify).
 */
class LinuxFallback implements FallbackInterface
{
    /**
     * Cached availability check result.
     */
    protected static ?bool $available = null;

    /**
     * Check if notify-send is available.
     */
    public function isAvailable(): bool
    {
        if (static::$available !== null) {
            return static::$available;
        }

        // Must be Linux
        if (PHP_OS_FAMILY !== 'Linux') {
            static::$available = false;

            return false;
        }

        // Check for notify-send command
        static::$available = $this->commandExists('notify-send');

        return static::$available;
    }

    /**
     * Send notification via notify-send.
     */
    public function send(string $message, ?string $title = null, ?int $urgency = null): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $args = [];

        // Map urgency to notify-send levels: low, normal, critical
        $urgencyMap = [0 => 'low', 1 => 'normal', 2 => 'critical'];
        $urgencyLevel = $urgencyMap[$urgency ?? 1] ?? 'normal';
        $args[] = '-u';
        $args[] = escapeshellarg($urgencyLevel);

        // Add title if provided
        if ($title !== null && $title !== '') {
            $args[] = escapeshellarg($title);
        } else {
            $args[] = escapeshellarg('Notification');
        }

        // Add message
        $args[] = escapeshellarg($message);

        $command = 'notify-send '.implode(' ', $args).' 2>/dev/null';

        exec($command, $output, $returnCode);

        return $returnCode === 0;
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
