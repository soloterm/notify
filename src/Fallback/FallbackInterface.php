<?php

declare(strict_types=1);

namespace SoloTerm\Notify\Fallback;

/**
 * Interface for external notification fallback providers.
 */
interface FallbackInterface
{
    /**
     * Check if this fallback is available on the current system.
     */
    public function isAvailable(): bool;

    /**
     * Send a notification using this fallback.
     *
     * @param  string  $message  The notification message.
     * @param  string|null  $title  Optional notification title.
     * @param  int|null  $urgency  Optional urgency level (0=low, 1=normal, 2=critical).
     * @return bool Whether the notification was sent successfully.
     */
    public function send(string $message, ?string $title = null, ?int $urgency = null): bool;
}
