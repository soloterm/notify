---
title: Advanced Features
description: Notification IDs, progress indicators, fallbacks, and testing.
---

# Advanced Features

## Notification IDs

With OSC 99 (Kitty), you can update or dismiss notifications using IDs.

### Updating Notifications

Perfect for progress indicators:

```php
// Initial notification
Notify::send('Building... 0%', 'Build', id: 'build-progress');

// Update the same notification
sleep(1);
Notify::send('Building... 25%', 'Build', id: 'build-progress');

sleep(1);
Notify::send('Building... 50%', 'Build', id: 'build-progress');

sleep(1);
Notify::send('Building... 100%', 'Build', id: 'build-progress');

// Final update
Notify::send('Build complete!', 'Build', id: 'build-progress');
```

### Dismissing Notifications

Close a notification by its ID:

```php
// Show a notification
Notify::send('Processing...', 'Status', id: 'status');

// Do some work...
processItems();

// Dismiss the notification
Notify::close('status');
```

### Check ID Support

```php
$caps = Notify::capabilities();

if ($caps['supports_id']) {
    // Use notification IDs
    Notify::send('Progress...', id: 'progress');
} else {
    // Fall back to regular notifications
    Notify::send('Progress...');
}
```

## Progress Indicator Pattern

```php
function processWithProgress(array $items): void
{
    $total = count($items);
    $caps = Notify::capabilities();
    $useId = $caps['supports_id'];

    foreach ($items as $i => $item) {
        $percent = round(($i + 1) / $total * 100);

        if ($useId) {
            Notify::send(
                "Processing: {$percent}% ({$i}/{$total})",
                'Batch Job',
                id: 'batch-progress'
            );
        }

        processItem($item);
    }

    // Final notification
    $message = "Completed {$total} items";
    if ($useId) {
        Notify::send($message, 'Batch Complete', id: 'batch-progress');
    } else {
        Notify::send($message, 'Batch Complete');
    }
}
```

## Fallback Configuration

### Disable Fallback

Prevent using system notification tools:

```php
Notify::disableFallback();

// Now only OSC notifications work
$sent = Notify::send('Test');  // false if OSC not supported
```

### Re-enable Fallback

```php
Notify::enableFallback();
```

### Check Fallback Availability

```php
if (Notify::canFallback()) {
    echo "System notifications available\n";
}
```

### Fallback Methods

```php
// Try OSC, then system tools, silently fail if neither works
Notify::sendAny('Works everywhere!', 'Hello');

// Skip OSC, go directly to system tools
Notify::sendExternal('Using system tools', 'Direct');

// Try OSC, then bell character
Notify::sendOrBell('OSC or beep', 'Fallback');
```

## Testing

### Custom Output Stream

For testing, redirect output to a custom stream:

```php
// Create a memory stream
$stream = fopen('php://memory', 'w+');

// Redirect notifications to the stream
Notify::setOutputStream($stream);

// Send a notification
Notify::send('Test message', 'Test');

// Read what was written
rewind($stream);
$output = stream_get_contents($stream);

// Verify the escape sequence
assert(str_contains($output, 'Test message'));
```

### Reset State

Clear cached detection for testing:

```php
Notify::reset();
```

This resets:
- Terminal detection
- Protocol selection
- Forced protocol
- Default urgency
- Fallback settings

### Mock Environment

Test different terminals by setting environment variables:

```php
// Simulate Kitty
putenv('KITTY_WINDOW_ID=12345');
Notify::reset();

assert(Notify::getTerminal() === 'kitty');
assert(Notify::getProtocol() === 'osc99');

// Clean up
putenv('KITTY_WINDOW_ID');
Notify::reset();
```

## Bell Character

The universal fallback - works in every terminal:

```php
// Just beep
Notify::bell();

// Send notification, fall back to bell
Notify::sendOrBell('Task complete');
```

## Force Protocol

Test specific protocols regardless of detected terminal:

```php
// Force OSC 9
Notify::forceProtocol('osc9');
Notify::send('Testing OSC 9');

// Force OSC 777
Notify::forceProtocol('osc777');
Notify::send('Testing OSC 777', 'Title');

// Force OSC 99
Notify::forceProtocol('osc99');
Notify::send('Testing OSC 99', 'Title', Notify::URGENCY_LOW, 'test-id');

// Reset to auto-detection
Notify::forceProtocol(null);
```

## Urgency Best Practices

Use urgency levels consistently:

```php
// LOW - Background tasks, informational
Notify::sendLow('Cache warmed');
Notify::sendLow('Backup complete');

// NORMAL (default) - Standard notifications
Notify::send('Build complete');
Notify::send('Tests passed');

// CRITICAL - Errors, requires attention
Notify::sendCritical('Build failed!');
Notify::sendCritical('Server unreachable');
```

## Conditional Notifications

Only notify when appropriate:

```php
// Only notify on completion, not during development
if (app()->environment('production')) {
    Notify::send('Deployment complete');
}

// Only notify for long operations
$startTime = microtime(true);
processLargeDataset();
$duration = microtime(true) - $startTime;

if ($duration > 30) {  // More than 30 seconds
    Notify::send("Completed in " . round($duration) . "s");
}
```

## Next Steps

- [Progress Bars](progress-bars) - Tab/taskbar progress indicators
- [Terminal Features](terminal-features) - Hyperlinks, attention, shell integration
- [Troubleshooting](troubleshooting) - Common issues and solutions
- [API Reference](api-reference) - Complete method reference
