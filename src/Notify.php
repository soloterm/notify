<?php

declare(strict_types=1);

namespace SoloTerm\Notify;

use SoloTerm\Notify\Fallback\FallbackChain;

/**
 * A PHP library for sending desktop notifications via OSC escape sequences.
 *
 * Supports multiple notification protocols (OSC 9, OSC 777, OSC 99) and
 * automatically detects terminal capabilities for optimal compatibility.
 */
class Notify
{
    /**
     * Urgency levels for notifications (OSC 99 only).
     */
    public const URGENCY_LOW = 0;

    public const URGENCY_NORMAL = 1;

    public const URGENCY_CRITICAL = 2;

    /**
     * Progress states for OSC 9;4 progress bars.
     */
    public const PROGRESS_HIDDEN = 0;

    public const PROGRESS_NORMAL = 1;

    public const PROGRESS_ERROR = 2;

    public const PROGRESS_INDETERMINATE = 3;

    public const PROGRESS_PAUSED = 4;

    /**
     * The detected terminal type.
     */
    protected static ?string $terminalType = null;

    /**
     * The selected OSC protocol.
     */
    protected static ?string $oscProtocol = null;

    /**
     * Whether detection has been performed.
     */
    protected static bool $detected = false;

    /**
     * Force a specific protocol (for testing or manual override).
     */
    protected static ?string $forcedProtocol = null;

    /**
     * Custom output stream (for testing).
     */
    protected static mixed $outputStream = null;

    /**
     * Default urgency level for OSC 99 notifications.
     */
    protected static int $defaultUrgency = self::URGENCY_NORMAL;

    /**
     * Whether external fallback (notify-send, osascript, etc.) is enabled.
     */
    protected static bool $fallbackEnabled = true;

    /**
     * Send a desktop notification via OSC escape sequences.
     *
     * @param  string  $message  The notification message/body.
     * @param  string|null  $title  Optional title (supported by OSC 777 and OSC 99).
     * @param  int|null  $urgency  Optional urgency level (OSC 99 only: 0=low, 1=normal, 2=critical).
     * @param  string|null  $id  Optional notification ID for updates (OSC 99 only).
     * @return bool Whether the notification was sent successfully.
     */
    public static function send(string $message, ?string $title = null, ?int $urgency = null, ?string $id = null): bool
    {
        if (! static::canNotify()) {
            return false;
        }

        $sequence = static::buildSequence($message, $title, $urgency, $id);

        if ($sequence === null) {
            return false;
        }

        return static::write($sequence);
    }

    /**
     * Close/dismiss a notification by its ID (OSC 99 only).
     *
     * @param  string  $id  The notification ID to close.
     * @return bool Whether the close sequence was sent successfully.
     */
    public static function close(string $id): bool
    {
        if (! static::canNotify()) {
            return false;
        }

        // Only OSC 99 supports notification IDs
        if (static::getProtocol() !== 'osc99') {
            return false;
        }

        $id = static::sanitizeId($id);
        if ($id === '') {
            return false;
        }

        // OSC 99 close notification format: ESC]99;i=<id>:p=close;ST
        $sequence = "\x1b]99;i={$id}:p=close;\x1b\\";
        $sequence = static::wrapForMultiplexer($sequence);

        return static::write($sequence);
    }

    /**
     * Send a notification with low urgency (OSC 99 only, others ignore urgency).
     */
    public static function sendLow(string $message, ?string $title = null): bool
    {
        return static::send($message, $title, self::URGENCY_LOW);
    }

    /**
     * Send a notification with critical urgency (OSC 99 only, others ignore urgency).
     */
    public static function sendCritical(string $message, ?string $title = null): bool
    {
        return static::send($message, $title, self::URGENCY_CRITICAL);
    }

    /**
     * Set the default urgency level for OSC 99 notifications.
     */
    public static function setDefaultUrgency(int $urgency): void
    {
        static::$defaultUrgency = max(0, min(2, $urgency));
    }

    /**
     * Check if notifications can be sent in the current environment.
     */
    public static function canNotify(): bool
    {
        // Must be CLI
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        // Must have STDOUT
        if (! defined('STDOUT')) {
            return false;
        }

        // Must be a TTY (unless we have a custom output stream for testing)
        if (static::$outputStream === null) {
            if (function_exists('stream_isatty') && ! stream_isatty(STDOUT)) {
                return false;
            }
        }

        // Must have a supported protocol
        return static::getProtocol() !== null;
    }

    /**
     * Get the detected terminal type.
     */
    public static function getTerminal(): ?string
    {
        static::detectOnce();

        return static::$terminalType;
    }

    /**
     * Get the selected OSC protocol.
     */
    public static function getProtocol(): ?string
    {
        if (static::$forcedProtocol !== null) {
            return static::$forcedProtocol;
        }

        static::detectOnce();

        return static::$oscProtocol;
    }

    /**
     * Force a specific protocol (useful for testing or manual override).
     *
     * @param  string|null  $protocol  One of: 'osc9', 'osc777', 'osc99', or null to auto-detect.
     */
    public static function forceProtocol(?string $protocol): void
    {
        static::$forcedProtocol = $protocol;
    }

    /**
     * Set a custom output stream (for testing).
     *
     * @param  resource|null  $stream
     */
    public static function setOutputStream(mixed $stream): void
    {
        static::$outputStream = $stream;
    }

    /**
     * Reset all static state (useful for testing).
     */
    public static function reset(): void
    {
        static::$terminalType = null;
        static::$oscProtocol = null;
        static::$detected = false;
        static::$forcedProtocol = null;
        static::$outputStream = null;
        static::$defaultUrgency = self::URGENCY_NORMAL;
        static::$fallbackEnabled = true;
        FallbackChain::reset();
    }

    /**
     * Get information about the current terminal's notification capabilities.
     *
     * @return array{terminal: ?string, protocol: ?string, supports_title: bool, supports_urgency: bool, supports_id: bool, supports_progress: bool, in_multiplexer: bool, fallback_available: bool}
     */
    public static function capabilities(): array
    {
        $protocol = static::getProtocol();

        return [
            'terminal' => static::getTerminal(),
            'protocol' => $protocol,
            'supports_title' => in_array($protocol, ['osc777', 'osc99'], true),
            'supports_urgency' => $protocol === 'osc99',
            'supports_id' => $protocol === 'osc99',
            'supports_progress' => static::supportsProgress(),
            'in_multiplexer' => static::inTmux() || static::inScreen(),
            'fallback_available' => static::canFallback(),
        ];
    }

    /**
     * Build the OSC escape sequence for the notification.
     */
    protected static function buildSequence(string $message, ?string $title, ?int $urgency = null, ?string $id = null): ?string
    {
        $protocol = static::getProtocol();

        $sequence = match ($protocol) {
            'osc9' => static::buildOsc9($message),
            'osc777' => static::buildOsc777($message, $title),
            'osc99' => static::buildOsc99($message, $title, $urgency ?? static::$defaultUrgency, $id),
            default => null,
        };

        if ($sequence === null) {
            return null;
        }

        // Wrap for multiplexer passthrough if needed
        return static::wrapForMultiplexer($sequence);
    }

    /**
     * Build an OSC 9 notification sequence (iTerm2 style, message only).
     *
     * Format: ESC ] 9 ; message BEL
     */
    protected static function buildOsc9(string $message): string
    {
        // OSC 9 only supports a message, no title
        $message = static::sanitize($message);

        return "\x1b]9;{$message}\x07";
    }

    /**
     * Build an OSC 777 notification sequence (rxvt-unicode style, title + body).
     *
     * Format: ESC ] 777 ; notify ; title ; body BEL
     */
    protected static function buildOsc777(string $message, ?string $title): string
    {
        $title = static::sanitize($title ?? 'Notification');
        $message = static::sanitize($message);

        return "\x1b]777;notify;{$title};{$message}\x07";
    }

    /**
     * Build an OSC 99 notification sequence (Kitty style, full-featured).
     *
     * Format: ESC ] 99 ; metadata ; payload ST
     * For simple notifications: ESC ] 99 ; ; message ST
     * For title + body: uses d=0/1 for done flag and p=title/body
     *
     * @param  int  $urgency  Urgency level: 0=low, 1=normal, 2=critical
     * @param  string|null  $id  Optional notification ID for updates/dismissal
     */
    protected static function buildOsc99(string $message, ?string $title, int $urgency = 1, ?string $id = null): string
    {
        $urgency = max(0, min(2, $urgency));
        $idMeta = '';

        if ($id !== null) {
            $id = static::sanitizeId($id);
            if ($id !== '') {
                $idMeta = "i={$id}";
            }
        }

        if ($title === null) {
            // Simple notification with just a body
            $message = static::sanitize($message);

            // Build metadata: ID and urgency (if not normal)
            $metaParts = [];
            if ($idMeta !== '') {
                $metaParts[] = $idMeta;
            }
            if ($urgency !== 1) {
                $metaParts[] = "u={$urgency}";
            }
            $metadata = implode(':', $metaParts);

            return "\x1b]99;{$metadata};{$message}\x1b\\";
        }

        // Multi-part notification with title and body
        $title = static::sanitize($title);
        $message = static::sanitize($message);

        // d=0 means "more parts coming", d=1 means "done"
        // p=title means this is the title, p=body means this is the body
        // i=<id> sets notification ID
        // u=N sets urgency level
        $titleMeta = 'd=0:p=title';
        if ($idMeta !== '') {
            $titleMeta .= ':' . $idMeta;
        }
        if ($urgency !== 1) {
            $titleMeta .= ":u={$urgency}";
        }

        return "\x1b]99;{$titleMeta};{$title}\x1b\\" .
               "\x1b]99;d=1:p=body;{$message}\x1b\\";
    }

    /**
     * Wrap the sequence for tmux or screen passthrough if needed.
     */
    protected static function wrapForMultiplexer(string $sequence): string
    {
        // Check for tmux
        if (static::inTmux()) {
            return static::wrapForTmux($sequence);
        }

        // Check for GNU Screen
        if (static::inScreen()) {
            return static::wrapForScreen($sequence);
        }

        return $sequence;
    }

    /**
     * Wrap sequence for tmux passthrough.
     *
     * Format: DCS tmux ; doubled_sequence ST
     * All ESC characters inside must be doubled.
     */
    protected static function wrapForTmux(string $sequence): string
    {
        // Double all ESC characters
        $doubled = str_replace("\x1b", "\x1b\x1b", $sequence);

        return "\x1bPtmux;{$doubled}\x1b\\";
    }

    /**
     * Wrap sequence for GNU Screen passthrough.
     *
     * Format: DCS sequence ST
     */
    protected static function wrapForScreen(string $sequence): string
    {
        return "\x1bP{$sequence}\x1b\\";
    }

    /**
     * Check if running inside tmux.
     */
    public static function inTmux(): bool
    {
        return getenv('TMUX') !== false && getenv('TMUX') !== '';
    }

    /**
     * Check if running inside GNU Screen.
     */
    public static function inScreen(): bool
    {
        return getenv('STY') !== false && getenv('STY') !== '';
    }

    /**
     * Sanitize a string for use in an OSC sequence.
     *
     * Removes control characters and semicolons that could break the sequence.
     */
    protected static function sanitize(string $str): string
    {
        // Remove control characters (except space)
        $str = preg_replace('/[\x00-\x1f\x7f]/', '', $str) ?? $str;

        // Replace semicolons with colons to avoid breaking OSC 777 parsing
        // (OSC 777 uses semicolons as delimiters)
        $str = str_replace(';', ':', $str);

        return $str;
    }

    /**
     * Sanitize a notification ID (OSC 99).
     *
     * IDs must only contain [a-zA-Z0-9_-+.] characters.
     */
    protected static function sanitizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-+.]/', '', $id) ?? '';
    }

    /**
     * Write the sequence to output.
     */
    protected static function write(string $sequence): bool
    {
        if (static::$outputStream !== null) {
            return fwrite(static::$outputStream, $sequence) !== false;
        }

        if (! defined('STDOUT')) {
            return false;
        }

        return fwrite(STDOUT, $sequence) !== false;
    }

    /**
     * Perform terminal detection once.
     */
    protected static function detectOnce(): void
    {
        if (static::$detected) {
            return;
        }

        static::$detected = true;
        static::$terminalType = static::detectTerminal();
        static::$oscProtocol = static::selectProtocol(static::$terminalType);
    }

    /**
     * Detect the current terminal emulator.
     */
    protected static function detectTerminal(): ?string
    {
        // Fast path: terminal-specific environment variables

        // Kitty
        if (getenv('KITTY_WINDOW_ID') !== false) {
            return 'kitty';
        }

        // iTerm2
        if (getenv('ITERM_SESSION_ID') !== false) {
            return 'iterm2';
        }

        // WezTerm
        if (getenv('WEZTERM_PANE') !== false) {
            return 'wezterm';
        }

        // Windows Terminal
        if (getenv('WT_SESSION') !== false) {
            return 'windows-terminal';
        }

        // Alacritty (no notification support, but detect anyway)
        if (getenv('ALACRITTY_WINDOW_ID') !== false) {
            return 'alacritty';
        }

        // Konsole
        if (getenv('KONSOLE_VERSION') !== false) {
            return 'konsole';
        }

        // Check TERM_PROGRAM
        $termProgram = getenv('TERM_PROGRAM');
        if ($termProgram !== false) {
            return match ($termProgram) {
                'iTerm.app' => 'iterm2',
                'WezTerm' => 'wezterm',
                'Apple_Terminal' => 'apple-terminal',
                'vscode' => 'vscode',
                'Hyper' => 'hyper',
                'ghostty' => 'ghostty',
                default => strtolower($termProgram),
            };
        }

        // VTE-based terminals (GNOME Terminal, etc.)
        if (getenv('VTE_VERSION') !== false) {
            return 'vte';
        }

        // Check for Ghostty via GHOSTTY_RESOURCES_DIR
        if (getenv('GHOSTTY_RESOURCES_DIR') !== false) {
            return 'ghostty';
        }

        return null;
    }

    /**
     * Send a terminal bell (BEL character) as a fallback notification.
     *
     * This can be used when OSC notifications aren't supported but you still
     * want some form of audio/visual alert.
     */
    public static function bell(): bool
    {
        if (PHP_SAPI !== 'cli' || ! defined('STDOUT')) {
            return false;
        }

        return static::write("\x07");
    }

    /**
     * Send a notification with fallback to external tools, then bell.
     *
     * Tries in order: OSC notification → external fallback → bell
     */
    public static function sendOrBell(string $message, ?string $title = null): bool
    {
        if (static::canNotify()) {
            return static::send($message, $title);
        }

        // Try external fallback before bell
        if (static::$fallbackEnabled && static::sendExternal($message, $title)) {
            return true;
        }

        return static::bell();
    }

    /**
     * Send notification using any available method (OSC or external fallback).
     *
     * Does NOT fall back to bell. Use sendOrBell() if you want bell fallback.
     */
    public static function sendAny(string $message, ?string $title = null, ?int $urgency = null): bool
    {
        // Try OSC first
        if (static::canNotify()) {
            return static::send($message, $title, $urgency);
        }

        // Try external fallback
        if (static::$fallbackEnabled) {
            return static::sendExternal($message, $title, $urgency);
        }

        return false;
    }

    /**
     * Send notification using external tools (notify-send, osascript, etc.).
     *
     * Bypasses OSC and directly uses system notification tools.
     */
    public static function sendExternal(string $message, ?string $title = null, ?int $urgency = null): bool
    {
        if (! static::$fallbackEnabled) {
            return false;
        }

        return FallbackChain::getInstance()->send($message, $title, $urgency);
    }

    /**
     * Enable external fallback notifications.
     */
    public static function enableFallback(bool $enabled = true): void
    {
        static::$fallbackEnabled = $enabled;
    }

    /**
     * Disable external fallback notifications.
     */
    public static function disableFallback(): void
    {
        static::$fallbackEnabled = false;
    }

    /**
     * Check if external fallback is available.
     */
    public static function canFallback(): bool
    {
        return static::$fallbackEnabled && FallbackChain::getInstance()->canFallback();
    }

    /**
     * Get a list of all supported terminal names.
     *
     * @return array<string, string|null> Terminal name => protocol used
     */
    public static function supportedTerminals(): array
    {
        return [
            'kitty' => 'osc99',
            'iterm2' => 'osc9',
            'wezterm' => 'osc777',
            'ghostty' => 'osc777',
            'vte' => 'osc777',
            'foot' => 'osc777',
            'alacritty' => null,
            'konsole' => null,
            'apple-terminal' => null,
            'windows-terminal' => null,
            'vscode' => null,
        ];
    }

    /**
     * Select the best OSC protocol for the detected terminal.
     */
    protected static function selectProtocol(?string $terminal): ?string
    {
        return match ($terminal) {
            // OSC 99 (Kitty protocol - most feature-rich)
            'kitty' => 'osc99',

            // OSC 9 (iTerm2 style - simple but widely supported)
            'iterm2' => 'osc9',

            // OSC 777 (supports title + body)
            'wezterm' => 'osc777',
            'ghostty' => 'osc777',
            'vte' => 'osc777', // May require patched VTE

            // Terminals with no notification support
            'alacritty' => null,
            'konsole' => null,
            'apple-terminal' => null,
            'vscode' => null,

            // Windows Terminal uses OSC 9 differently (for progress bars)
            'windows-terminal' => null,

            // Unknown terminal - try OSC 9 as safest fallback
            default => $terminal !== null ? 'osc9' : null,
        };
    }

    // ========================================================================
    // Progress Bar Support (OSC 9;4)
    // Supported by: Windows Terminal, Ghostty (1.2+), iTerm2 (3.6.6+), ConEmu, Mintty
    // ========================================================================

    /**
     * Check if the current terminal supports OSC 9;4 progress bars.
     *
     * Supported terminals:
     * - Windows Terminal
     * - Ghostty (1.2+)
     * - iTerm2 (3.6.6+)
     * - ConEmu
     * - Mintty
     */
    public static function supportsProgress(): bool
    {
        $terminal = static::getTerminal();

        return match ($terminal) {
            'windows-terminal', 'ghostty' => true,
            'iterm2' => static::compareVersion(getenv('TERM_PROGRAM_VERSION') ?: '', '3.6.6') >= 0,
            default => false,
        };
    }

    /**
     * Compare two semantic version strings.
     *
     * Returns -1 if a < b, 0 if a == b, 1 if a > b.
     * Handles versions like "3.6.6" or "3.6.6-beta".
     */
    protected static function compareVersion(string $a, string $b): int
    {
        // Strip any suffix after hyphen (e.g., "3.6.6-beta" -> "3.6.6")
        $a = explode('-', $a)[0];
        $b = explode('-', $b)[0];

        $partsA = explode('.', $a);
        $partsB = explode('.', $b);

        $maxLen = max(count($partsA), count($partsB));

        for ($i = 0; $i < $maxLen; $i++) {
            $numA = (int) ($partsA[$i] ?? 0);
            $numB = (int) ($partsB[$i] ?? 0);

            if ($numA < $numB) {
                return -1;
            }
            if ($numA > $numB) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Show a progress bar in the terminal tab/taskbar.
     *
     * On terminals that don't support OSC 9;4, this method returns false
     * without sending anything. Use supportsProgress() to check support first.
     *
     * @param  int  $progress  Progress percentage (0-100)
     * @param  int  $state  Progress state (use PROGRESS_* constants)
     */
    public static function progress(int $progress, int $state = self::PROGRESS_NORMAL): bool
    {
        if (! static::supportsProgress()) {
            return false;
        }

        $progress = max(0, min(100, $progress));
        $state = max(0, min(4, $state));

        $sequence = "\x1b]9;4;{$state};{$progress}\x07";
        $sequence = static::wrapForMultiplexer($sequence);

        return static::write($sequence);
    }

    /**
     * Clear/hide the progress bar.
     *
     * Returns false if progress bars aren't supported by the current terminal.
     */
    public static function progressClear(): bool
    {
        if (! static::supportsProgress()) {
            return false;
        }

        // Send hidden state directly to bypass the support check in progress()
        $sequence = "\x1b]9;4;0;0\x07";
        $sequence = static::wrapForMultiplexer($sequence);

        return static::write($sequence);
    }

    /**
     * Show error progress (red).
     *
     * Returns false if progress bars aren't supported by the current terminal.
     */
    public static function progressError(int $progress = 100): bool
    {
        return static::progress($progress, self::PROGRESS_ERROR);
    }

    /**
     * Show paused progress (yellow).
     *
     * Returns false if progress bars aren't supported by the current terminal.
     */
    public static function progressPaused(int $progress): bool
    {
        return static::progress($progress, self::PROGRESS_PAUSED);
    }

    /**
     * Show indeterminate/pulsing progress.
     *
     * Returns false if progress bars aren't supported by the current terminal.
     */
    public static function progressIndeterminate(): bool
    {
        return static::progress(0, self::PROGRESS_INDETERMINATE);
    }

    // ========================================================================
    // Request Attention (OSC 1337 - iTerm2)
    // ========================================================================

    /**
     * Request attention by bouncing the dock icon (iTerm2 on macOS).
     *
     * @param  bool  $fireworks  Show fireworks animation instead of simple bounce
     */
    public static function requestAttention(bool $fireworks = false): bool
    {
        $value = $fireworks ? 'fireworks' : 'yes';
        $sequence = "\x1b]1337;RequestAttention={$value}\x07";
        $sequence = static::wrapForMultiplexer($sequence);

        return static::write($sequence);
    }

    /**
     * Request attention with fireworks animation (iTerm2).
     */
    public static function fireworks(): bool
    {
        return static::requestAttention(true);
    }

    /**
     * Steal focus - bring iTerm2 window to front.
     */
    public static function stealFocus(): bool
    {
        $sequence = "\x1b]1337;StealFocus\x07";
        $sequence = static::wrapForMultiplexer($sequence);

        return static::write($sequence);
    }

    // ========================================================================
    // Hyperlinks (OSC 8)
    // Supported by: iTerm2, VTE, kitty, WezTerm, Windows Terminal, etc.
    // ========================================================================

    /**
     * Create a clickable hyperlink in terminal output.
     *
     * @param  string  $url  The URL to link to
     * @param  string|null  $text  Display text (defaults to URL if not provided)
     * @param  string|null  $id  Optional ID to group multiple hyperlinks
     */
    public static function hyperlink(string $url, ?string $text = null, ?string $id = null): string
    {
        if ($url === '') {
            return $text ?? '';
        }

        $text ??= $url;

        $params = $id !== null ? "id={$id}" : '';

        return "\x1b]8;{$params};{$url}\x07{$text}\x1b]8;;\x07";
    }

    // ========================================================================
    // Shell Integration (OSC 133)
    // Supported by: Windows Terminal, WezTerm, VS Code terminal, kitty, iTerm2
    // ========================================================================

    /**
     * Mark the start of a shell prompt.
     */
    public static function shellPromptStart(): bool
    {
        $sequence = "\x1b]133;A\x07";

        return static::write($sequence);
    }

    /**
     * Mark the end of prompt / start of user input.
     */
    public static function shellCommandStart(): bool
    {
        $sequence = "\x1b]133;B\x07";

        return static::write($sequence);
    }

    /**
     * Mark the start of command execution.
     */
    public static function shellCommandExecuted(): bool
    {
        $sequence = "\x1b]133;C\x07";

        return static::write($sequence);
    }

    /**
     * Mark the end of command execution.
     *
     * @param  int  $exitCode  The command's exit code
     */
    public static function shellCommandFinished(int $exitCode = 0): bool
    {
        $sequence = "\x1b]133;D;{$exitCode}\x07";

        return static::write($sequence);
    }
}
