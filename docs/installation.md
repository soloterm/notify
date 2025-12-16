---
title: Installation
description: How to install the Notify package.
---

# Installation

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.1 or higher |

No other dependencies required. Notify is pure PHP.

## Install via Composer

```bash
composer require soloterm/notify
```

For development-only use:

```bash
composer require soloterm/notify --dev
```

## Verify Installation

Test that notifications work in your terminal:

```php
<?php

require 'vendor/autoload.php';

use SoloTerm\Notify\Notify;

// Check terminal detection
echo "Terminal: " . (Notify::getTerminal() ?? 'Unknown') . "\n";
echo "Protocol: " . (Notify::getProtocol() ?? 'None') . "\n";
echo "Can notify: " . (Notify::canNotify() ? 'Yes' : 'No') . "\n";

// Send a test notification
if (Notify::canNotify()) {
    Notify::send('Installation successful!', 'Notify');
    echo "Notification sent!\n";
} else {
    echo "OSC notifications not supported, trying fallback...\n";
    Notify::sendAny('Installation successful!', 'Notify');
}
```

Save as `test-notify.php` and run:

```bash
php test-notify.php
```

## Laravel Integration

For Laravel applications, install the Laravel wrapper:

```bash
composer require soloterm/notify-laravel --dev
```

See [notify-laravel documentation](/docs/notify-laravel) for Laravel-specific features.

## Terminal Setup

### tmux

For notifications inside tmux, enable passthrough in `~/.tmux.conf`:

```bash
set -g allow-passthrough on
```

Reload the configuration:

```bash
tmux source-file ~/.tmux.conf
```

### GNU Screen

No configuration needed. Notify automatically wraps sequences for Screen.

### Unsupported Terminals

For terminals without OSC support (Alacritty, Terminal.app, VS Code), Notify falls back to system tools:

- **Linux**: Requires `notify-send` (usually from `libnotify`)
- **macOS**: Uses built-in `osascript`
- **Windows**: Uses PowerShell toast notifications

## Check Capabilities

Get detailed information about your terminal's support:

```php
$caps = Notify::capabilities();

print_r($caps);
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
```

## Next Steps

- [Basic Usage](basic-usage) - Learn the core API
- [Terminal Support](terminal-support) - Full compatibility details
