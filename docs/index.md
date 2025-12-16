---
title: Introduction
description: Send desktop notifications from PHP terminal applications via OSC escape sequences.
---

# Notify

Notify is a pure PHP library for sending desktop notifications from CLI applications. Instead of relying on external tools like `notify-send` or `osascript`, it uses OSC (Operating System Command) escape sequences that modern terminal emulators interpret directly.

## The Problem

Building CLI applications often requires notifying users when long-running tasks complete:

- Build finished
- Tests passed (or failed)
- Queue job completed
- Deployment done

Traditional approaches require:

- **Platform-specific tools**: `notify-send` on Linux, `osascript` on macOS, PowerShell on Windows
- **External dependencies**: Shell commands, system binaries
- **Complex detection**: Check the platform, find the right tool, handle errors

## The Solution

Notify writes escape sequences directly to STDOUT. Modern terminals interpret these as desktop notifications:

```php
use SoloTerm\Notify\Notify;

Notify::send('Build complete!', 'My App');
```

No dependencies. No shell commands. Just pure PHP.

## Key Features

### Pure PHP

No external dependencies. Works anywhere PHP runs.

### Automatic Detection

Detects your terminal and selects the optimal notification protocol:

```php
$terminal = Notify::getTerminal();  // 'kitty', 'iterm2', 'wezterm', etc.
$protocol = Notify::getProtocol();  // 'osc9', 'osc777', 'osc99'
```

### Multiple Protocols

Supports three OSC notification protocols:

| Protocol | Terminals | Features |
|----------|-----------|----------|
| OSC 9 | iTerm2 | Simple, message-only |
| OSC 777 | WezTerm, Ghostty, VTE | Title + body |
| OSC 99 | Kitty | Urgency, IDs, full-featured |
| OSC 9;4 | Windows Terminal, Ghostty, iTerm2 | Progress bars |
| OSC 8 | Most modern terminals | Hyperlinks |
| OSC 133 | VS Code, WezTerm, Kitty | Shell integration |

### Multiplexer Support

Works inside tmux and GNU Screen with automatic passthrough wrapping.

### Graceful Fallback

When OSC isn't supported, falls back to system notification tools:

- **Linux**: `notify-send`
- **macOS**: `osascript`
- **Windows**: PowerShell toast notifications

## Quick Start

Install via Composer:

```bash
composer require soloterm/notify
```

Send a notification:

```php
use SoloTerm\Notify\Notify;

// Simple notification
Notify::send('Task complete!');

// With title
Notify::send('All 142 tests passed', 'Tests');

// Check support first
if (Notify::canNotify()) {
    Notify::send('This terminal supports notifications!');
}
```

## Real-World Examples

### After a Build

```php
$startTime = microtime(true);

// ... build logic ...

$duration = round(microtime(true) - $startTime, 2);
Notify::send("Completed in {$duration}s", 'Build Finished');
```

### Test Results

```php
$passed = $this->runTests();

if ($passed) {
    Notify::send('All tests passed!', 'Success');
} else {
    Notify::sendCritical('Some tests failed!', 'Tests');
}
```

### Queue Worker

```php
while ($job = $queue->pop()) {
    $job->process();
    Notify::send("Processed: {$job->name}", 'Queue');
}
```

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.1 or higher |

For full functionality, use a [supported terminal](terminal-support). Unsupported terminals fall back to system notification tools.

## Laravel Integration

For Laravel applications, use the [notify-laravel](/docs/notify-laravel) package which adds:

- Facade with semantic methods (`success`, `error`, `warning`, `info`)
- Artisan command
- Scheduler macros
- Logging channel
- Event integration

## Next Steps

- [Installation](installation) - Get started
- [Basic Usage](basic-usage) - Learn the core API
- [Terminal Support](terminal-support) - Check compatibility
- [Progress Bars](progress-bars) - Tab/taskbar progress indicators
- [Terminal Features](terminal-features) - Hyperlinks, attention, shell integration
