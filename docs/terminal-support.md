---
title: Terminal Support
description: Terminal compatibility, OSC protocols, and multiplexer configuration.
---

# Terminal Support

Notify automatically detects your terminal and selects the best notification protocol.

## Supported Terminals

| Terminal | Support | Protocol | Notes |
|----------|:-------:|:--------:|-------|
| **Kitty** | Full | OSC 99 | Urgency, IDs, all features |
| **iTerm2** | Full | OSC 9 | macOS, message-only |
| **WezTerm** | Full | OSC 777 | Cross-platform, title + body |
| **Ghostty** | Full | OSC 777 | Cross-platform, title + body |
| **Foot** | Full | OSC 777 | Wayland terminal |
| **VTE-based** | Partial | OSC 777 | GNOME Terminal, etc. (may need patched VTE) |
| **tmux** | Full | Passthrough | Requires configuration |
| **GNU Screen** | Full | Passthrough | Automatic |
| **Alacritty** | Fallback | — | Uses system tools |
| **Terminal.app** | Fallback | — | Uses osascript |
| **VS Code** | Fallback | — | Uses system tools |
| **Windows Terminal** | Fallback | — | Uses PowerShell |
| **Konsole** | Fallback | — | Uses system tools |

## OSC Protocols

### OSC 9 (iTerm2)

The simplest protocol. Message only, no title support.

```
ESC ] 9 ; message BEL
```

**Used by**: iTerm2

**Features**:
- Basic notifications
- Widely compatible
- No title, urgency, or ID support

### OSC 777 (rxvt-unicode)

Supports separate title and body.

```
ESC ] 777 ; notify ; title ; body BEL
```

**Used by**: WezTerm, Ghostty, Foot, VTE-based terminals

**Features**:
- Title and body
- More descriptive notifications
- No urgency or ID support

### OSC 99 (Kitty)

The most feature-rich protocol.

```
ESC ] 99 ; metadata ; payload ST
```

**Used by**: Kitty

**Features**:
- Title and body
- Urgency levels (low, normal, critical)
- Notification IDs (update/dismiss)
- Multi-part notifications

## Terminal Detection

Notify checks these environment variables:

| Variable | Terminal |
|----------|----------|
| `KITTY_WINDOW_ID` | Kitty |
| `ITERM_SESSION_ID` | iTerm2 |
| `WEZTERM_PANE` | WezTerm |
| `GHOSTTY_RESOURCES_DIR` | Ghostty |
| `TERM_PROGRAM=Apple_Terminal` | Terminal.app |
| `TERM_PROGRAM=vscode` | VS Code |
| `TERM=foot*` | Foot |
| `VTE_VERSION` | VTE-based |
| `KONSOLE_VERSION` | Konsole |

Check detection results:

```php
echo Notify::getTerminal();  // 'kitty', 'iterm2', etc.
echo Notify::getProtocol();  // 'osc9', 'osc777', 'osc99'
```

## Multiplexer Support

### tmux

tmux requires passthrough configuration for OSC sequences to reach the outer terminal.

Add to `~/.tmux.conf`:

```bash
set -g allow-passthrough on
```

Reload configuration:

```bash
tmux source-file ~/.tmux.conf
```

Check if running in tmux:

```php
if (Notify::inTmux()) {
    echo "Running inside tmux\n";
}
```

### GNU Screen

Screen passthrough is automatic. No configuration needed.

Check if running in Screen:

```php
if (Notify::inScreen()) {
    echo "Running inside GNU Screen\n";
}
```

## Forcing a Protocol

Override automatic detection:

```php
// Force a specific protocol
Notify::forceProtocol('osc777');
Notify::send('Using OSC 777', 'Forced');

// Reset to auto-detection
Notify::forceProtocol(null);
```

Valid protocol values:
- `'osc9'` - iTerm2 style
- `'osc777'` - WezTerm/Ghostty style
- `'osc99'` - Kitty style
- `null` - Auto-detect

## Fallback System

When OSC isn't supported, Notify can use system notification tools:

### Linux

Uses `notify-send` from libnotify:

```bash
# Install on Ubuntu/Debian
sudo apt install libnotify-bin

# Install on Fedora
sudo dnf install libnotify
```

### macOS

Uses built-in `osascript` with AppleScript. No installation needed.

### Windows

Uses PowerShell toast notifications. No installation needed.

### Check Fallback Availability

```php
if (Notify::canFallback()) {
    echo "System notifications available\n";
}
```

### Control Fallback

```php
// Disable fallback
Notify::disableFallback();

// Re-enable fallback
Notify::enableFallback();

// Use fallback explicitly
Notify::sendExternal('Message', 'Title');
```

## Feature Capabilities

Check what your terminal supports:

```php
$caps = Notify::capabilities();

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

### Feature Matrix

| Feature | OSC 9 | OSC 777 | OSC 99 |
|---------|:-----:|:-------:|:------:|
| Message | Yes | Yes | Yes |
| Title | No | Yes | Yes |
| Urgency | No | No | Yes |
| Notification IDs | No | No | Yes |

## List All Terminals

Get all known terminals and their protocols:

```php
$terminals = Notify::supportedTerminals();

// [
//     'kitty' => 'osc99',
//     'iterm2' => 'osc9',
//     'wezterm' => 'osc777',
//     'ghostty' => 'osc777',
//     'foot' => 'osc777',
//     'alacritty' => null,
//     'terminal_app' => null,
//     // ...
// ]
```

## Next Steps

- [Advanced Features](advanced) - IDs, progress indicators, testing
- [Troubleshooting](troubleshooting) - Common issues
