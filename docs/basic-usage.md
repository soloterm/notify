---
title: Basic Usage
description: Learn the core Notify API for sending desktop notifications.
---

# Basic Usage

## Sending Notifications

### Simple Message

```php
use SoloTerm\Notify\Notify;

Notify::send('Task complete!');
```

### With Title

```php
Notify::send('All 142 tests passed in 3.2s', 'Tests Passed');
```

### Multiple Arguments

```php
Notify::send(
    message: 'Build finished successfully',
    title: 'My App',
    urgency: Notify::URGENCY_NORMAL,
    id: 'build-status'
);
```

## Checking Support

### Can Notify

Check if OSC notifications are supported:

```php
if (Notify::canNotify()) {
    Notify::send('This will work!');
} else {
    echo "OSC not supported\n";
}
```

### Get Terminal

Detect the current terminal:

```php
$terminal = Notify::getTerminal();
// Returns: 'kitty', 'iterm2', 'wezterm', 'ghostty', etc.
// Returns null if terminal is unknown
```

### Get Protocol

Get the selected notification protocol:

```php
$protocol = Notify::getProtocol();
// Returns: 'osc9', 'osc777', 'osc99', or null
```

## Urgency Levels

Set notification priority (OSC 99/Kitty only):

```php
// Explicit urgency
Notify::send('Background task done', 'Info', Notify::URGENCY_LOW);
Notify::send('Build complete', 'Success', Notify::URGENCY_NORMAL);
Notify::send('Server down!', 'Alert', Notify::URGENCY_CRITICAL);

// Convenience methods
Notify::sendLow('Low priority message');
Notify::sendCritical('Critical alert!', 'Error');
```

### Urgency Constants

| Constant | Value | Use Case |
|----------|-------|----------|
| `URGENCY_LOW` | 0 | Background tasks, informational |
| `URGENCY_NORMAL` | 1 | Standard notifications (default) |
| `URGENCY_CRITICAL` | 2 | Errors, alerts, requires attention |

### Set Default Urgency

```php
// All subsequent notifications use this urgency
Notify::setDefaultUrgency(Notify::URGENCY_LOW);
```

## Fallback Options

### Bell Character

Send a terminal bell (works everywhere):

```php
Notify::bell();
```

### Try Notification, Then Bell

```php
// Tries OSC first, falls back to bell
Notify::sendOrBell('Task complete', 'Done');
```

### Try Any Available Method

```php
// Tries OSC, then system tools, then gives up silently
Notify::sendAny('This works everywhere!', 'Hello');
```

### System Tools Only

```php
// Bypass OSC, use notify-send/osascript/PowerShell directly
Notify::sendExternal('Message', 'Title');
```

## Real-World Examples

### Build Process

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
    Notify::send('All tests passed!', 'Tests');
} else {
    Notify::sendCritical('Some tests failed!', 'Tests');
}
```

### Long-Running Script

```php
// Notify when script completes
register_shutdown_function(function() {
    Notify::sendAny('Script finished', 'PHP');
});
```

### Batch Processing

```php
$total = count($items);
foreach ($items as $i => $item) {
    processItem($item);

    // Notify on completion
    if ($i === $total - 1) {
        Notify::send("Processed {$total} items", 'Batch Complete');
    }
}
```

## Return Values

All send methods return `bool`:

```php
$sent = Notify::send('Message');

if ($sent) {
    echo "Notification sent successfully\n";
} else {
    echo "Failed to send notification\n";
}
```

## Next Steps

- [Terminal Support](terminal-support) - Compatibility details
- [Advanced Features](advanced) - IDs, fallbacks, testing
