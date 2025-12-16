---
title: Progress Bars
description: Display progress indicators in terminal tabs and taskbars using OSC 9;4.
---

# Progress Bars

Display progress indicators in terminal tabs and taskbars using OSC 9;4 escape sequences.

## Overview

Progress bars appear in your terminal's tab or taskbar icon, providing visual feedback for long-running operations without cluttering the terminal output.

```php
use SoloTerm\Notify\Notify;

// Show 50% progress
Notify::progress(50);

// Clear when done
Notify::progressClear();
```

## Terminal Support

| Terminal | Support | Notes |
|----------|:-------:|-------|
| **Windows Terminal** | Full | Native support |
| **Ghostty** | Full | Requires 1.2+ |
| **iTerm2** | Full | Requires 3.6.6+ |
| **ConEmu** | Full | Windows |
| **Mintty** | Full | Windows/Cygwin |
| **Other terminals** | None | Sequences ignored |

Check if progress bars are supported:

```php
if (Notify::supportsProgress()) {
    Notify::progress(50);
}
```

## Basic Usage

### Show Progress

```php
// Show percentage (0-100)
Notify::progress(0);   // Just started
Notify::progress(50);  // Halfway
Notify::progress(100); // Complete
```

### Clear Progress

```php
// Hide the progress bar
Notify::progressClear();
```

## Progress States

Progress bars support different visual states:

### Normal (Default)

Standard progress indicator:

```php
Notify::progress(75);
// or explicitly:
Notify::progress(75, Notify::PROGRESS_NORMAL);
```

### Error State

Red progress bar indicating failure:

```php
// Show error at current progress
Notify::progressError(75);

// Show error at 100%
Notify::progressError();
```

### Paused State

Yellow progress bar indicating paused operation:

```php
Notify::progressPaused(60);
```

### Indeterminate State

Pulsing/spinning indicator for unknown duration:

```php
Notify::progressIndeterminate();
```

## State Constants

| Constant | Value | Appearance |
|----------|:-----:|------------|
| `PROGRESS_HIDDEN` | 0 | Hidden |
| `PROGRESS_NORMAL` | 1 | Default color |
| `PROGRESS_ERROR` | 2 | Red |
| `PROGRESS_INDETERMINATE` | 3 | Pulsing |
| `PROGRESS_PAUSED` | 4 | Yellow |

## Real-World Examples

### File Processing

```php
$files = glob('*.txt');
$total = count($files);

if (Notify::supportsProgress()) {
    Notify::progress(0);
}

foreach ($files as $i => $file) {
    processFile($file);

    if (Notify::supportsProgress()) {
        $percent = (int) (($i + 1) / $total * 100);
        Notify::progress($percent);
    }
}

Notify::progressClear();
Notify::send('Processing complete!', 'Files');
```

### Build Process

```php
function build(): void
{
    $steps = ['compile', 'test', 'package', 'deploy'];
    $total = count($steps);

    foreach ($steps as $i => $step) {
        $percent = (int) ($i / $total * 100);
        Notify::progress($percent);

        try {
            runStep($step);
        } catch (Exception $e) {
            Notify::progressError($percent);
            throw $e;
        }
    }

    Notify::progress(100);
    sleep(1); // Show completion briefly
    Notify::progressClear();
}
```

### Download with Pause

```php
class Downloader
{
    private bool $paused = false;

    public function download(string $url): void
    {
        $total = getFileSize($url);
        $downloaded = 0;

        while ($downloaded < $total) {
            if ($this->paused) {
                Notify::progressPaused((int) ($downloaded / $total * 100));
                sleep(1);
                continue;
            }

            $chunk = downloadChunk($url, $downloaded);
            $downloaded += strlen($chunk);

            Notify::progress((int) ($downloaded / $total * 100));
        }

        Notify::progressClear();
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }
}
```

### Unknown Duration

```php
// Start indeterminate progress
Notify::progressIndeterminate();

// Do work with unknown duration
$result = longRunningOperation();

// Switch to determinate when known
Notify::progress(50);

// More work...
finalizeOperation($result);

Notify::progress(100);
Notify::progressClear();
```

## Combining with Notifications

Progress bars work well with desktop notifications:

```php
// Start progress
Notify::progress(0);

foreach ($jobs as $i => $job) {
    $job->process();
    Notify::progress((int) (($i + 1) / count($jobs) * 100));
}

// Clear progress and notify
Notify::progressClear();
Notify::send('All jobs completed!', 'Queue');
```

## Best Practices

1. **Always clear progress** when done, even on errors
2. **Check support first** with `supportsProgress()` for critical paths
3. **Use error state** to indicate failures visually
4. **Combine with notifications** for completion alerts
5. **Use indeterminate** for operations with unknown duration

## Next Steps

- [Terminal-Specific Features](terminal-features) - Hyperlinks, attention requests
- [API Reference](api-reference) - Complete method documentation
