<?php

declare(strict_types=1);

namespace SoloTerm\Notify\Fallback;

/**
 * Manages a chain of notification fallbacks, trying each until one succeeds.
 */
class FallbackChain
{
    /**
     * Registered fallback providers.
     *
     * @var FallbackInterface[]
     */
    protected array $fallbacks = [];

    /**
     * Singleton instance.
     */
    protected static ?FallbackChain $instance = null;

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self;
            static::$instance->registerDefaults();
        }

        return static::$instance;
    }

    /**
     * Reset the singleton instance (for testing).
     */
    public static function reset(): void
    {
        static::$instance = null;
        LinuxFallback::reset();
        MacOSFallback::reset();
        WindowsFallback::reset();
    }

    /**
     * Register the default fallback providers.
     */
    public function registerDefaults(): void
    {
        $this->register(new LinuxFallback);
        $this->register(new MacOSFallback);
        $this->register(new WindowsFallback);
    }

    /**
     * Register a fallback provider.
     */
    public function register(FallbackInterface $fallback): void
    {
        $this->fallbacks[] = $fallback;
    }

    /**
     * Get the first available fallback provider.
     */
    public function getAvailable(): ?FallbackInterface
    {
        foreach ($this->fallbacks as $fallback) {
            if ($fallback->isAvailable()) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * Send a notification using the first available fallback.
     *
     * @param  string  $message  The notification message.
     * @param  string|null  $title  Optional notification title.
     * @param  int|null  $urgency  Optional urgency level (0=low, 1=normal, 2=critical).
     * @return bool Whether the notification was sent successfully.
     */
    public function send(string $message, ?string $title = null, ?int $urgency = null): bool
    {
        $fallback = $this->getAvailable();

        if ($fallback === null) {
            return false;
        }

        return $fallback->send($message, $title, $urgency);
    }

    /**
     * Check if any fallback is available.
     */
    public function canFallback(): bool
    {
        return $this->getAvailable() !== null;
    }
}
