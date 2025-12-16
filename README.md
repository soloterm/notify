# Notify

[![Latest Version on Packagist](https://img.shields.io/packagist/v/soloterm/notify.svg?style=flat-square)](https://packagist.org/packages/soloterm/notify)
[![Total Downloads](https://img.shields.io/packagist/dt/soloterm/notify.svg?style=flat-square)](https://packagist.org/packages/soloterm/notify)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

A PHP library for sending desktop notifications via OSC escape sequences in terminal applications.

This library was built to support [Solo](https://github.com/soloterm/solo), your all-in-one Laravel command to tame local development.

## Why Use This Library?

Building CLI applications often requires notifying users when long-running tasks complete:

* Build finished
* Tests passed (or failed)
* Queue job completed
* Deployment done

Instead of relying on external tools like `notify-send` or `osascript`, this library uses OSC (Operating System Command) escape sequences that modern terminal emulators interpret directly. No dependencies, no shell commands, just pure PHP.

**When OSC isn't supported**, the library automatically falls back to system notification tools (`notify-send` on Linux, `osascript` on macOS, PowerShell on Windows).

## Installation

```bash
composer require soloterm/notify
```

## Usage

```php
use SoloTerm\Notify\Notify;

// Simple notification (message only)
Notify::send('Build complete!');

// Notification with title and body
Notify::send('All 142 tests passed in 3.2s', 'Tests Passed');

// Check if notifications are supported
if (Notify::canNotify()) {
    Notify::send('This will work!');
}

// Get detected terminal
$terminal = Notify::getTerminal(); // 'iterm2', 'kitty', 'wezterm', etc.

// Get selected protocol
$protocol = Notify::getProtocol(); // 'osc9', 'osc777', 'osc99'
```

### Real-World Examples

```php
// After a long build process
$startTime = microtime(true);
// ... build logic ...
$duration = round(microtime(true) - $startTime, 2);
Notify::send("Completed in {$duration}s", 'Build Finished');

// After running tests
$result = $testsPassed ? 'All tests passed!' : 'Some tests failed';
$title = $testsPassed ? 'Tests Passed' : 'Tests Failed';
Notify::send($result, $title);

// In a queue worker
while ($job = $queue->pop()) {
    $job->process();
    Notify::send("Processed: {$job->name}", 'Queue');
}
```

## Terminal Compatibility

| Terminal | Support | Protocol | Notes |
|----------|:-------:|:--------:|-------|
| **iTerm2** | ✅ | OSC 9 | macOS |
| **Kitty** | ✅ | OSC 99 | Cross-platform, full-featured |
| **WezTerm** | ✅ | OSC 777 | Cross-platform |
| **Ghostty** | ✅ | OSC 777 | Cross-platform |
| **Foot** | ✅ | OSC 777 | Wayland |
| **tmux** | ✅ | Passthrough | Requires `allow-passthrough on` |
| **GNU Screen** | ✅ | Passthrough | Automatic wrapping |
| **Alacritty** | ⚠️ | Fallback | Uses system notifications |
| **Terminal.app** | ⚠️ | Fallback | Uses system notifications |
| **VS Code** | ⚠️ | Fallback | Uses system notifications |
| **Windows Terminal** | ⚠️ | Fallback | Uses system notifications |

### tmux Configuration

For notifications to work inside tmux, add this to your `~/.tmux.conf`:

```bash
set -g allow-passthrough on
```

Then reload your tmux configuration:

```bash
tmux source-file ~/.tmux.conf
```

## OSC Protocols

This library supports three notification protocols:

### OSC 9 (iTerm2)
Simple message-only notifications. Widely supported.
```
ESC ] 9 ; message BEL
```

### OSC 777 (rxvt-unicode)
Supports separate title and body. Used by WezTerm, Ghostty, and VTE-based terminals.
```
ESC ] 777 ; notify ; title ; body BEL
```

### OSC 99 (Kitty)
The most feature-rich protocol with support for urgency levels, notification IDs, and more.
```
ESC ] 99 ; metadata ; payload ST
```

## Advanced Usage

### Urgency Levels (Kitty/OSC 99 only)

```php
use SoloTerm\Notify\Notify;

// Send with explicit urgency
Notify::send('Background task done', 'Info', Notify::URGENCY_LOW);
Notify::send('Build complete', 'Success', Notify::URGENCY_NORMAL);
Notify::send('Server down!', 'Alert', Notify::URGENCY_CRITICAL);

// Convenience methods
Notify::sendLow('Low priority message');
Notify::sendCritical('Critical alert!');

// Set default urgency for all notifications
Notify::setDefaultUrgency(Notify::URGENCY_LOW);
```

### Notification IDs (Kitty/OSC 99 only)

Update or dismiss existing notifications using IDs - perfect for progress indicators:

```php
// Send notification with an ID
Notify::send('Building... 0%', 'Build', id: 'build-progress');

// Update the same notification
Notify::send('Building... 50%', 'Build', id: 'build-progress');
Notify::send('Building... 100%', 'Build', id: 'build-progress');

// Close/dismiss a notification by ID
Notify::close('build-progress');

// Check if the terminal supports IDs
$caps = Notify::capabilities();
if ($caps['supports_id']) {
    // Use notification IDs
}
```

### External Fallback

When OSC notifications aren't supported, the library can fall back to system tools:

- **Linux**: `notify-send` (libnotify)
- **macOS**: `osascript` (AppleScript)
- **Windows**: PowerShell toast notifications

```php
// Fallback is enabled by default - disable it if needed
Notify::disableFallback();

// Re-enable fallback
Notify::enableFallback();

// Check if fallback is available
if (Notify::canFallback()) {
    echo "System notifications available as fallback\n";
}

// Send using any available method (OSC or fallback)
Notify::sendAny('This works everywhere!', 'Hello');

// Send directly via external tools (bypassing OSC)
Notify::sendExternal('Message', 'Title');
```

### Bell Fallback

```php
// Send bell character (works everywhere)
Notify::bell();

// Try notification first, then external fallback, then bell
Notify::sendOrBell('Task complete', 'Done');
```

### Check Capabilities

```php
$caps = Notify::capabilities();
// Returns:
// [
//     'terminal' => 'kitty',
//     'protocol' => 'osc99',
//     'supports_title' => true,
//     'supports_urgency' => true,
//     'supports_id' => true,
//     'supports_progress' => true,
//     'in_multiplexer' => false,
//     'fallback_available' => true,
// ]

// List all known terminals and their support
$terminals = Notify::supportedTerminals();
// ['kitty' => 'osc99', 'iterm2' => 'osc9', 'alacritty' => null, ...]
```

### Force a Specific Protocol

```php
// Force OSC 777 regardless of detected terminal
Notify::forceProtocol('osc777');
Notify::send('Using OSC 777', 'Forced');

// Reset to auto-detection
Notify::forceProtocol(null);
```

### Check Multiplexer Status

```php
if (Notify::inTmux()) {
    echo "Running inside tmux\n";
}

if (Notify::inScreen()) {
    echo "Running inside GNU Screen\n";
}
```

### Reset State

```php
// Useful for testing or when terminal changes
Notify::reset();
```

### Progress Bars (OSC 9;4)

Display progress in your terminal's tab or taskbar:

```php
// Check if supported
if (Notify::supportsProgress()) {
    // Show progress (0-100)
    Notify::progress(50);

    // Different states
    Notify::progressError(75);        // Red - error state
    Notify::progressPaused(60);       // Yellow - paused
    Notify::progressIndeterminate();  // Pulsing - unknown duration

    // Clear when done
    Notify::progressClear();
}
```

**Supported terminals**: Windows Terminal, Ghostty 1.2+, iTerm2 3.6.6+, ConEmu, Mintty

### Hyperlinks (OSC 8)

Create clickable links in terminal output:

```php
echo Notify::hyperlink('https://example.com', 'Click here');
echo Notify::hyperlink('https://example.com'); // URL as display text
```

### Request Attention (iTerm2 only)

```php
Notify::requestAttention();           // Bounce dock icon
Notify::fireworks();                  // Fireworks animation
Notify::stealFocus();                 // Bring window to front
```

## How It Works

1. **Terminal Detection**: The library checks environment variables like `KITTY_WINDOW_ID`, `ITERM_SESSION_ID`, `WEZTERM_PANE`, and `TERM_PROGRAM` to identify the terminal.

2. **Protocol Selection**: Based on the detected terminal, the optimal OSC protocol is selected.

3. **Sequence Building**: The notification message is sanitized and formatted according to the protocol.

4. **Multiplexer Passthrough**: If running inside tmux or GNU Screen, the sequence is wrapped in a DCS (Device Control String) passthrough.

5. **Output**: The escape sequence is written directly to STDOUT, which the terminal interprets and displays as a desktop notification.

6. **Fallback**: If OSC isn't supported, the library automatically tries system notification tools.

## Requirements

* PHP 8.1 or higher
* A supported terminal emulator (or system notification tools for fallback)
* For tmux: `allow-passthrough on` in your config

## Testing

```bash
composer test
```

## Contributing

Contributions are welcome! Please feel free to submit a pull request.

## License

The MIT License (MIT).

## Support

This is free! If you want to support me:

* Check out my courses:
    * [Database School](https://databaseschool.com)
    * [Screencasting](https://screencasting.com)
* Help spread the word about things I make

## Credits

Solo was developed by Aaron Francis. If you like it, please let me know!

* Twitter: https://twitter.com/aarondfrancis
* Website: https://aaronfrancis.com
* YouTube: https://youtube.com/@aarondfrancis
* GitHub: https://github.com/aarondfrancis/solo

## Related Projects

- [Solo](https://github.com/soloterm/solo) - All-in-one Laravel command for local development
- [Screen](https://github.com/soloterm/screen) - Pure PHP terminal renderer
- [Dumps](https://github.com/soloterm/dumps) - Laravel command to intercept dumps
- [Grapheme](https://github.com/soloterm/grapheme) - Unicode grapheme width calculator
- [Notify Laravel](https://github.com/soloterm/notify-laravel) - Laravel integration for soloterm/notify
- [TNotify](https://github.com/soloterm/tnotify) - Standalone, cross-platform CLI for desktop notifications
- [VTail](https://github.com/soloterm/vtail) - Vendor-aware tail for Laravel logs
