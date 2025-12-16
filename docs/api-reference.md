---
title: API Reference
description: Complete API documentation for the Notify class.
---

# API Reference

Complete reference for all public methods and constants.

## Notify Class

```php
use SoloTerm\Notify\Notify;
```

All methods are static.

---

## Sending Notifications

### send()

```php
public static function send(
    string $message,
    ?string $title = null,
    ?int $urgency = null,
    ?string $id = null
): bool
```

Send a notification via OSC escape sequences.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$message` | string | Notification body text |
| `$title` | ?string | Optional title (protocol-dependent) |
| `$urgency` | ?int | Urgency level (OSC 99 only) |
| `$id` | ?string | Notification ID for updates (OSC 99 only) |

**Returns**: `true` if sent successfully, `false` otherwise.

```php
Notify::send('Build complete!');
Notify::send('All tests passed', 'Tests');
Notify::send('Alert!', 'Error', Notify::URGENCY_CRITICAL);
Notify::send('Progress...', 'Build', id: 'build-1');
```

---

### sendLow()

```php
public static function sendLow(string $message, ?string $title = null): bool
```

Send with low urgency (OSC 99 only).

```php
Notify::sendLow('Background task complete');
```

---

### sendCritical()

```php
public static function sendCritical(string $message, ?string $title = null): bool
```

Send with critical urgency (OSC 99 only).

```php
Notify::sendCritical('Server down!', 'Alert');
```

---

### close()

```php
public static function close(string $id): bool
```

Close/dismiss a notification by ID (OSC 99 only).

```php
Notify::send('Processing...', id: 'status');
// ... later ...
Notify::close('status');
```

---

### bell()

```php
public static function bell(): bool
```

Send a terminal bell character. Works everywhere.

```php
Notify::bell();
```

---

### sendOrBell()

```php
public static function sendOrBell(string $message, ?string $title = null): bool
```

Try OSC notification, fall back to bell.

```php
Notify::sendOrBell('Task complete');
```

---

### sendAny()

```php
public static function sendAny(
    string $message,
    ?string $title = null,
    ?int $urgency = null
): bool
```

Try OSC, then system tools, silently fail if neither works.

```php
Notify::sendAny('Works everywhere!', 'Hello');
```

---

### sendExternal()

```php
public static function sendExternal(
    string $message,
    ?string $title = null,
    ?int $urgency = null
): bool
```

Send via system tools only (notify-send, osascript, PowerShell).

```php
Notify::sendExternal('Using system notifications');
```

---

## Detection Methods

### canNotify()

```php
public static function canNotify(): bool
```

Check if OSC notifications are supported.

```php
if (Notify::canNotify()) {
    Notify::send('Supported!');
}
```

---

### canFallback()

```php
public static function canFallback(): bool
```

Check if system notification tools are available.

```php
if (Notify::canFallback()) {
    Notify::sendExternal('Fallback available');
}
```

---

### getTerminal()

```php
public static function getTerminal(): ?string
```

Get the detected terminal name.

**Returns**: Terminal identifier or `null` if unknown.

| Value | Terminal |
|-------|----------|
| `'kitty'` | Kitty |
| `'iterm2'` | iTerm2 |
| `'wezterm'` | WezTerm |
| `'ghostty'` | Ghostty |
| `'foot'` | Foot |
| `'vte'` | VTE-based |
| `'terminal_app'` | macOS Terminal |
| `'vscode'` | VS Code |
| `'alacritty'` | Alacritty |
| `null` | Unknown |

```php
echo Notify::getTerminal();  // 'kitty'
```

---

### getProtocol()

```php
public static function getProtocol(): ?string
```

Get the selected OSC protocol.

**Returns**: `'osc9'`, `'osc777'`, `'osc99'`, or `null`.

```php
echo Notify::getProtocol();  // 'osc99'
```

---

### inTmux()

```php
public static function inTmux(): bool
```

Check if running inside tmux.

```php
if (Notify::inTmux()) {
    echo "Inside tmux\n";
}
```

---

### inScreen()

```php
public static function inScreen(): bool
```

Check if running inside GNU Screen.

```php
if (Notify::inScreen()) {
    echo "Inside GNU Screen\n";
}
```

---

## Configuration

### forceProtocol()

```php
public static function forceProtocol(?string $protocol): void
```

Override automatic protocol detection.

| Value | Protocol |
|-------|----------|
| `'osc9'` | iTerm2 style |
| `'osc777'` | WezTerm/Ghostty style |
| `'osc99'` | Kitty style |
| `null` | Auto-detect |

```php
Notify::forceProtocol('osc777');
Notify::send('Using OSC 777');
Notify::forceProtocol(null);  // Reset
```

---

### setDefaultUrgency()

```php
public static function setDefaultUrgency(int $urgency): void
```

Set default urgency for all notifications.

```php
Notify::setDefaultUrgency(Notify::URGENCY_LOW);
```

---

### enableFallback()

```php
public static function enableFallback(bool $enabled = true): void
```

Enable or disable system notification fallback.

```php
Notify::enableFallback(true);
Notify::enableFallback(false);
```

---

### disableFallback()

```php
public static function disableFallback(): void
```

Disable system notification fallback.

```php
Notify::disableFallback();
```

---

### setOutputStream()

```php
public static function setOutputStream($stream): void
```

Set custom output stream (for testing).

```php
$stream = fopen('php://memory', 'w+');
Notify::setOutputStream($stream);
```

---

### reset()

```php
public static function reset(): void
```

Reset all cached state and settings.

```php
Notify::reset();
```

---

## Introspection

### capabilities()

```php
public static function capabilities(): array
```

Get detailed capability information.

**Returns**:

```php
[
    'terminal' => 'kitty',
    'protocol' => 'osc99',
    'supports_title' => true,
    'supports_urgency' => true,
    'supports_id' => true,
    'supports_progress' => true,
    'in_multiplexer' => false,
    'fallback_available' => true,
]
```

---

### supportedTerminals()

```php
public static function supportedTerminals(): array
```

Get all known terminals and their protocols.

**Returns**:

```php
[
    'kitty' => 'osc99',
    'iterm2' => 'osc9',
    'wezterm' => 'osc777',
    'ghostty' => 'osc777',
    'foot' => 'osc777',
    'alacritty' => null,
    // ...
]
```

---

## Constants

### Urgency Levels

```php
Notify::URGENCY_LOW      // 0
Notify::URGENCY_NORMAL   // 1
Notify::URGENCY_CRITICAL // 2
```

### Progress States

```php
Notify::PROGRESS_HIDDEN        // 0
Notify::PROGRESS_NORMAL        // 1
Notify::PROGRESS_ERROR         // 2
Notify::PROGRESS_INDETERMINATE // 3
Notify::PROGRESS_PAUSED        // 4
```

---

## Progress Bar Methods

### supportsProgress()

```php
public static function supportsProgress(): bool
```

Check if the current terminal supports progress bars (OSC 9;4).

**Supported terminals**: Windows Terminal, Ghostty 1.2+, iTerm2 3.6.6+, ConEmu, Mintty

```php
if (Notify::supportsProgress()) {
    Notify::progress(50);
}
```

---

### progress()

```php
public static function progress(int $progress, int $state = self::PROGRESS_NORMAL): bool
```

Show a progress bar in the terminal tab/taskbar.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$progress` | int | Progress percentage (0-100) |
| `$state` | int | Progress state constant |

**Returns**: `false` if not supported.

```php
Notify::progress(50);
Notify::progress(75, Notify::PROGRESS_NORMAL);
```

---

### progressClear()

```php
public static function progressClear(): bool
```

Clear/hide the progress bar.

```php
Notify::progressClear();
```

---

### progressError()

```php
public static function progressError(int $progress = 100): bool
```

Show error progress (red).

```php
Notify::progressError();      // Error at 100%
Notify::progressError(75);    // Error at 75%
```

---

### progressPaused()

```php
public static function progressPaused(int $progress): bool
```

Show paused progress (yellow).

```php
Notify::progressPaused(60);
```

---

### progressIndeterminate()

```php
public static function progressIndeterminate(): bool
```

Show indeterminate/pulsing progress.

```php
Notify::progressIndeterminate();
```

---

## Hyperlink Methods

### hyperlink()

```php
public static function hyperlink(
    string $url,
    ?string $text = null,
    ?string $id = null
): string
```

Create a clickable hyperlink in terminal output.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$url` | string | The URL to link to |
| `$text` | ?string | Display text (defaults to URL) |
| `$id` | ?string | Optional ID to group multiple hyperlinks |

**Returns**: The formatted hyperlink string.

```php
echo Notify::hyperlink('https://example.com', 'Click here');
echo Notify::hyperlink('https://example.com');
echo Notify::hyperlink('https://example.com', 'Link', 'group1');
```

---

## Request Attention Methods (iTerm2)

### requestAttention()

```php
public static function requestAttention(bool $fireworks = false): bool
```

Request attention by bouncing the dock icon (iTerm2 on macOS).

| Parameter | Type | Description |
|-----------|------|-------------|
| `$fireworks` | bool | Show fireworks animation instead of simple bounce |

```php
Notify::requestAttention();
Notify::requestAttention(fireworks: true);
```

---

### fireworks()

```php
public static function fireworks(): bool
```

Request attention with fireworks animation (iTerm2).

```php
Notify::fireworks();
```

---

### stealFocus()

```php
public static function stealFocus(): bool
```

Steal focus - bring iTerm2 window to front.

```php
Notify::stealFocus();
```

---

## Shell Integration Methods (OSC 133)

### shellPromptStart()

```php
public static function shellPromptStart(): bool
```

Mark the start of a shell prompt.

**Supported by**: Windows Terminal, WezTerm, VS Code terminal, Kitty, iTerm2

```php
Notify::shellPromptStart();
echo "$ ";
```

---

### shellCommandStart()

```php
public static function shellCommandStart(): bool
```

Mark the end of prompt / start of user input.

```php
Notify::shellCommandStart();
$input = readline();
```

---

### shellCommandExecuted()

```php
public static function shellCommandExecuted(): bool
```

Mark the start of command execution.

```php
Notify::shellCommandExecuted();
passthru($command);
```

---

### shellCommandFinished()

```php
public static function shellCommandFinished(int $exitCode = 0): bool
```

Mark the end of command execution.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$exitCode` | int | The command's exit code |

```php
Notify::shellCommandFinished(0);   // Success
Notify::shellCommandFinished(1);   // Failure
```

---

## FallbackInterface

For custom fallback providers:

```php
interface FallbackInterface
{
    public function isAvailable(): bool;
    public function send(string $message, ?string $title, ?int $urgency): bool;
}
```

Built-in implementations:

- `LinuxFallback` - Uses `notify-send`
- `MacOSFallback` - Uses `osascript`
- `WindowsFallback` - Uses PowerShell toast notifications
