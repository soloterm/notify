---
title: Troubleshooting
description: Common issues and solutions for Notify.
---

# Troubleshooting

## Notifications Not Appearing

### Check Terminal Support

First, verify your terminal is detected:

```php
echo "Terminal: " . (Notify::getTerminal() ?? 'Unknown') . "\n";
echo "Protocol: " . (Notify::getProtocol() ?? 'None') . "\n";
echo "Can notify: " . (Notify::canNotify() ? 'Yes' : 'No') . "\n";
```

If terminal is "Unknown", your terminal may not support OSC notifications.

### Try Fallback

Use system notification tools instead:

```php
// This tries OSC first, then system tools
Notify::sendAny('Test notification', 'Test');

// Or use system tools directly
Notify::sendExternal('Test notification', 'Test');
```

### Check Fallback Availability

```php
if (Notify::canFallback()) {
    echo "Fallback available\n";
} else {
    echo "No fallback available\n";

    // On Linux, install libnotify
    // sudo apt install libnotify-bin
}
```

## tmux Issues

### Notifications Don't Work in tmux

Enable passthrough in `~/.tmux.conf`:

```bash
set -g allow-passthrough on
```

Reload the configuration:

```bash
tmux source-file ~/.tmux.conf
```

Or restart tmux completely.

### Verify tmux Detection

```php
if (Notify::inTmux()) {
    echo "Running in tmux (detected)\n";
} else {
    echo "Not in tmux\n";
}
```

### Check TMUX Environment Variable

```bash
echo $TMUX
```

If empty, you're not in tmux.

## Terminal Not Detected

### Check Environment Variables

Notify uses environment variables to detect terminals:

```bash
# Check what's set
env | grep -E 'KITTY|ITERM|WEZTERM|GHOSTTY|TERM'
```

### Force a Protocol

If detection fails but you know your terminal:

```php
// Force the protocol manually
Notify::forceProtocol('osc777');  // For WezTerm, Ghostty
Notify::forceProtocol('osc9');    // For iTerm2
Notify::forceProtocol('osc99');   // For Kitty

Notify::send('Test', 'Forced Protocol');
```

## Linux Fallback Not Working

### Install libnotify

```bash
# Ubuntu/Debian
sudo apt install libnotify-bin

# Fedora
sudo dnf install libnotify

# Arch
sudo pacman -S libnotify
```

### Test notify-send

```bash
notify-send "Test" "This should appear"
```

If this doesn't work, check your desktop environment's notification daemon.

## macOS Fallback Not Working

### Check osascript

```bash
osascript -e 'display notification "Test" with title "Test"'
```

If this fails, check System Preferences > Notifications for your terminal app.

### Notification Permissions

Your terminal may need notification permissions. Check:

System Preferences > Notifications > [Your Terminal App]

## Windows Fallback Not Working

### Check PowerShell

Open PowerShell and test:

```powershell
[Windows.UI.Notifications.ToastNotificationManager, Windows.UI.Notifications, ContentType = WindowsRuntime] | Out-Null
$template = [Windows.UI.Notifications.ToastNotificationManager]::GetTemplateContent([Windows.UI.Notifications.ToastTemplateType]::ToastText01)
$text = $template.GetElementsByTagName("text")
$text.Item(0).AppendChild($template.CreateTextNode("Test")) | Out-Null
$toast = [Windows.UI.Notifications.ToastNotification]::new($template)
[Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier("Notify").Show($toast)
```

## Debugging

### Full Capabilities Dump

```php
print_r(Notify::capabilities());
```

Output:

```
Array
(
    [terminal] => kitty
    [protocol] => osc99
    [supports_title] => 1
    [supports_urgency] => 1
    [supports_id] => 1
    [in_multiplexer] =>
    [fallback_available] => 1
)
```

### List All Known Terminals

```php
print_r(Notify::supportedTerminals());
```

### Reset and Retry

Clear cached state and try again:

```php
Notify::reset();

// Now detection runs fresh
echo Notify::getTerminal();
```

### Test Raw Escape Sequence

Write the sequence manually:

```php
// OSC 9 (iTerm2)
echo "\033]9;Test message\007";

// OSC 777 (WezTerm, Ghostty)
echo "\033]777;notify;Title;Message\007";

// OSC 99 (Kitty)
echo "\033]99;d=0;Title\033\\";
echo "\033]99;d=1:p=body;Message\033\\";
```

## Common Issues

### "Method not found" Errors

Ensure you're using the correct namespace:

```php
use SoloTerm\Notify\Notify;

// Not
use Notify;
```

### Notifications Work in Terminal but Not in Script

Check if STDOUT is available:

```php
if (!defined('STDOUT')) {
    echo "STDOUT not defined\n";
}

if (!is_resource(STDOUT)) {
    echo "STDOUT is not a resource\n";
}
```

### Notifications Appear Garbled

Your terminal may not support the selected protocol. Try forcing a simpler one:

```php
Notify::forceProtocol('osc9');
```

## Getting Help

If issues persist:

1. Check your terminal's documentation for notification support
2. Test with a minimal script
3. Report issues at [GitHub](https://github.com/soloterm/notify/issues)

## Next Steps

- [API Reference](api-reference) - Complete method documentation
- [Terminal Support](terminal-support) - Compatibility details
