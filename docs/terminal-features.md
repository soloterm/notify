---
title: Terminal-Specific Features
description: Hyperlinks, attention requests, and shell integration for specific terminals.
---

# Terminal-Specific Features

Beyond notifications, Notify supports additional terminal features like hyperlinks, attention requests, and shell integration.

## Hyperlinks (OSC 8)

Create clickable links in terminal output.

### Terminal Support

| Terminal | Support |
|----------|:-------:|
| iTerm2 | Full |
| Kitty | Full |
| WezTerm | Full |
| Windows Terminal | Full |
| VTE-based (GNOME Terminal) | Full |
| Foot | Full |
| Alacritty | Partial |

### Basic Usage

```php
use SoloTerm\Notify\Notify;

// Create a clickable link
echo Notify::hyperlink('https://example.com', 'Click here');
// Output: clickable "Click here" text

// URL as display text
echo Notify::hyperlink('https://example.com');
// Output: clickable "https://example.com" text
```

### Grouped Links

Use IDs to group multiple links (e.g., for multi-line URLs):

```php
// Same ID groups links together
echo Notify::hyperlink('https://example.com', 'Part 1', 'link-group');
echo Notify::hyperlink('https://example.com', 'Part 2', 'link-group');
```

### Real-World Examples

```php
// Git commit output
$hash = 'abc1234';
$url = "https://github.com/user/repo/commit/{$hash}";
echo "Commit: " . Notify::hyperlink($url, $hash) . "\n";

// Error with documentation link
$docUrl = 'https://docs.example.com/errors/E001';
echo "Error E001: " . Notify::hyperlink($docUrl, 'See documentation') . "\n";

// File paths (local URLs)
$file = '/path/to/file.php';
echo "File: " . Notify::hyperlink("file://{$file}", basename($file)) . "\n";
```

---

## Request Attention (OSC 1337)

Request user attention on macOS with iTerm2.

### Terminal Support

| Terminal | Support |
|----------|:-------:|
| iTerm2 | Full |
| Other terminals | None |

### Bounce Dock Icon

```php
// Simple bounce
Notify::requestAttention();

// With fireworks animation
Notify::requestAttention(fireworks: true);
```

### Fireworks Animation

```php
Notify::fireworks();
```

Shows a fireworks animation in iTerm2.

### Steal Focus

Bring the terminal window to the front:

```php
Notify::stealFocus();
```

### Real-World Examples

```php
// Alert on critical error
try {
    riskyOperation();
} catch (CriticalException $e) {
    Notify::requestAttention();
    Notify::sendCritical($e->getMessage(), 'Critical Error');
}

// Celebrate successful deployment
if ($deploymentSuccessful) {
    Notify::fireworks();
    Notify::send('Deployment complete!', 'Deploy');
}

// Bring terminal to front when input needed
if ($needsUserInput) {
    Notify::stealFocus();
    $input = readline('Enter value: ');
}
```

---

## Shell Integration (OSC 133)

Mark command boundaries for semantic terminal navigation.

### Terminal Support

| Terminal | Support |
|----------|:-------:|
| VS Code Terminal | Full |
| Windows Terminal | Full |
| WezTerm | Full |
| Kitty | Full |
| iTerm2 | Full |

### Why Use Shell Integration?

Shell integration enables:
- **Jump between commands** - Navigate to previous/next prompt
- **Select command output** - Easily copy output of a specific command
- **Re-run commands** - Click to re-execute previous commands
- **Scroll to command** - Jump to a command's output

### Command Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│  shellPromptStart()    shellCommandStart()                  │
│         ↓                      ↓                            │
│  ┌──────────────┐    ┌─────────────────┐                   │
│  │   $ _        │ → │ $ command       │                    │
│  │   prompt     │    │   user types    │                   │
│  └──────────────┘    └─────────────────┘                   │
│                              ↓                              │
│                    shellCommandExecuted()                   │
│                              ↓                              │
│                    ┌─────────────────┐                     │
│                    │ output...       │                     │
│                    │ more output...  │                     │
│                    └─────────────────┘                     │
│                              ↓                              │
│                    shellCommandFinished(0)                  │
└─────────────────────────────────────────────────────────────┘
```

### Basic Usage

```php
// Mark prompt start
Notify::shellPromptStart();
echo "$ ";

// Mark end of prompt (user is typing)
Notify::shellCommandStart();
$command = readline();

// Mark command execution start
Notify::shellCommandExecuted();
$exitCode = executeCommand($command);

// Mark command completion with exit code
Notify::shellCommandFinished($exitCode);
```

### Custom Shell Example

```php
class CustomShell
{
    public function run(): void
    {
        while (true) {
            // Start of prompt
            Notify::shellPromptStart();
            $prompt = $this->getPrompt();
            echo $prompt;

            // End of prompt, start of input
            Notify::shellCommandStart();
            $input = readline();

            if ($input === 'exit') {
                break;
            }

            // Command execution
            Notify::shellCommandExecuted();
            $exitCode = $this->execute($input);

            // Command finished
            Notify::shellCommandFinished($exitCode);
        }
    }

    private function getPrompt(): string
    {
        return getcwd() . ' $ ';
    }

    private function execute(string $command): int
    {
        // Execute and return exit code
        passthru($command, $exitCode);
        return $exitCode;
    }
}
```

### Integration with Existing Tools

```php
// PHPUnit runner with shell integration
function runTests(): int
{
    Notify::shellPromptStart();
    echo "Running tests...\n";
    Notify::shellCommandStart();
    Notify::shellCommandExecuted();

    $exitCode = 0;
    passthru('vendor/bin/phpunit', $exitCode);

    Notify::shellCommandFinished($exitCode);

    return $exitCode;
}
```

---

## Feature Detection

Check which features are available:

```php
$caps = Notify::capabilities();

// Check specific capabilities
if ($caps['supports_progress']) {
    Notify::progress(50);
}

// Terminal-specific features
$terminal = Notify::getTerminal();
if ($terminal === 'iterm2') {
    Notify::requestAttention();
}
```

## Next Steps

- [Progress Bars](progress-bars) - Tab/taskbar progress indicators
- [API Reference](api-reference) - Complete method documentation
