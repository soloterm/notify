<?php

declare(strict_types=1);

namespace SoloTerm\Notify\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoloTerm\Notify\Notify;

class NotifyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notify::reset();
    }

    protected function tearDown(): void
    {
        Notify::reset();
        parent::tearDown();
    }

    // =========================================================================
    // OSC 9 Tests
    // =========================================================================

    public function test_osc9_simple_message(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send('Hello'));

        $this->assertSame("\x1b]9;Hello\x07", $output);
    }

    public function test_osc9_ignores_title(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send('Body', 'Title'));

        // OSC 9 doesn't support titles, so it only uses the body
        $this->assertSame("\x1b]9;Body\x07", $output);
    }

    public function test_osc9_sanitizes_control_characters(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send("Hello\x00World\x1b"));

        $this->assertSame("\x1b]9;HelloWorld\x07", $output);
    }

    public function test_osc9_sanitizes_semicolons(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send('Hello; World'));

        $this->assertSame("\x1b]9;Hello: World\x07", $output);
    }

    // =========================================================================
    // OSC 777 Tests
    // =========================================================================

    public function test_osc777_with_title_and_body(): void
    {
        Notify::forceProtocol('osc777');
        $output = $this->captureOutput(fn () => Notify::send('Body text', 'Title'));

        $this->assertSame("\x1b]777;notify;Title;Body text\x07", $output);
    }

    public function test_osc777_default_title(): void
    {
        Notify::forceProtocol('osc777');
        $output = $this->captureOutput(fn () => Notify::send('Body text'));

        $this->assertSame("\x1b]777;notify;Notification;Body text\x07", $output);
    }

    public function test_osc777_sanitizes_semicolons_in_title(): void
    {
        Notify::forceProtocol('osc777');
        $output = $this->captureOutput(fn () => Notify::send('Body', 'Title; Part 2'));

        $this->assertSame("\x1b]777;notify;Title: Part 2;Body\x07", $output);
    }

    public function test_osc777_sanitizes_semicolons_in_body(): void
    {
        Notify::forceProtocol('osc777');
        $output = $this->captureOutput(fn () => Notify::send('Body; Part 2', 'Title'));

        $this->assertSame("\x1b]777;notify;Title;Body: Part 2\x07", $output);
    }

    // =========================================================================
    // OSC 99 Tests
    // =========================================================================

    public function test_osc99_simple_message(): void
    {
        Notify::forceProtocol('osc99');
        $output = $this->captureOutput(fn () => Notify::send('Hello'));

        $this->assertSame("\x1b]99;;Hello\x1b\\", $output);
    }

    public function test_osc99_with_title_and_body(): void
    {
        Notify::forceProtocol('osc99');
        $output = $this->captureOutput(fn () => Notify::send('Body', 'Title'));

        $expected = "\x1b]99;d=0:p=title;Title\x1b\\".
                    "\x1b]99;d=1:p=body;Body\x1b\\";

        $this->assertSame($expected, $output);
    }

    public function test_osc99_with_low_urgency(): void
    {
        Notify::forceProtocol('osc99');
        $output = $this->captureOutput(fn () => Notify::send('Hello', null, Notify::URGENCY_LOW));

        $this->assertSame("\x1b]99;u=0;Hello\x1b\\", $output);
    }

    public function test_osc99_with_critical_urgency(): void
    {
        Notify::forceProtocol('osc99');
        $output = $this->captureOutput(fn () => Notify::send('Alert!', null, Notify::URGENCY_CRITICAL));

        $this->assertSame("\x1b]99;u=2;Alert!\x1b\\", $output);
    }

    public function test_osc99_with_title_and_urgency(): void
    {
        Notify::forceProtocol('osc99');
        $output = $this->captureOutput(fn () => Notify::send('Body', 'Title', Notify::URGENCY_CRITICAL));

        $expected = "\x1b]99;d=0:p=title:u=2;Title\x1b\\".
                    "\x1b]99;d=1:p=body;Body\x1b\\";

        $this->assertSame($expected, $output);
    }

    public function test_send_low_helper(): void
    {
        Notify::forceProtocol('osc99');
        $output = $this->captureOutput(fn () => Notify::sendLow('Low priority'));

        $this->assertSame("\x1b]99;u=0;Low priority\x1b\\", $output);
    }

    public function test_send_critical_helper(): void
    {
        Notify::forceProtocol('osc99');
        $output = $this->captureOutput(fn () => Notify::sendCritical('Critical!'));

        $this->assertSame("\x1b]99;u=2;Critical!\x1b\\", $output);
    }

    public function test_default_urgency_setting(): void
    {
        Notify::forceProtocol('osc99');
        Notify::setDefaultUrgency(Notify::URGENCY_LOW);
        $output = $this->captureOutput(fn () => Notify::send('Hello'));

        $this->assertSame("\x1b]99;u=0;Hello\x1b\\", $output);
    }

    // =========================================================================
    // Multiplexer Passthrough Tests
    // =========================================================================

    public function test_tmux_passthrough_wrapping(): void
    {
        $this->withEnv(['TMUX' => '/tmp/tmux-1000/default,12345,0'], function () {
            Notify::reset();
            Notify::forceProtocol('osc9');
            $output = $this->captureOutput(fn () => Notify::send('Hello'));

            // ESC characters should be doubled inside the passthrough
            // Original: \x1b]9;Hello\x07
            // Wrapped:  \x1bPtmux;\x1b\x1b]9;Hello\x07\x1b\\
            $this->assertSame("\x1bPtmux;\x1b\x1b]9;Hello\x07\x1b\\", $output);
        });
    }

    public function test_screen_passthrough_wrapping(): void
    {
        $this->withEnv(['STY' => '12345.pts-0.hostname'], function () {
            Notify::reset();
            Notify::forceProtocol('osc9');
            $output = $this->captureOutput(fn () => Notify::send('Hello'));

            // Screen passthrough doesn't double ESC
            $this->assertSame("\x1bP\x1b]9;Hello\x07\x1b\\", $output);
        });
    }

    public function test_in_tmux_detection(): void
    {
        $this->withEnv(['TMUX' => '/tmp/tmux-1000/default,12345,0'], function () {
            Notify::reset();
            $this->assertTrue(Notify::inTmux());
            $this->assertFalse(Notify::inScreen());
        });
    }

    public function test_in_screen_detection(): void
    {
        $this->withEnv(['STY' => '12345.pts-0.hostname'], function () {
            Notify::reset();
            $this->assertTrue(Notify::inScreen());
            $this->assertFalse(Notify::inTmux());
        });
    }

    // =========================================================================
    // Terminal Detection Tests
    // =========================================================================

    public function test_detect_kitty(): void
    {
        $this->withEnv(['KITTY_WINDOW_ID' => '1'], function () {
            Notify::reset();
            $this->assertSame('kitty', Notify::getTerminal());
            $this->assertSame('osc99', Notify::getProtocol());
        });
    }

    public function test_detect_iterm2_via_session(): void
    {
        $this->withEnv(['ITERM_SESSION_ID' => 'w0t0p0:12345'], function () {
            Notify::reset();
            $this->assertSame('iterm2', Notify::getTerminal());
            $this->assertSame('osc9', Notify::getProtocol());
        });
    }

    public function test_detect_iterm2_via_term_program(): void
    {
        $this->withEnv(['TERM_PROGRAM' => 'iTerm.app'], function () {
            Notify::reset();
            $this->assertSame('iterm2', Notify::getTerminal());
            $this->assertSame('osc9', Notify::getProtocol());
        });
    }

    public function test_detect_wezterm(): void
    {
        $this->withEnv(['WEZTERM_PANE' => '0'], function () {
            Notify::reset();
            $this->assertSame('wezterm', Notify::getTerminal());
            $this->assertSame('osc777', Notify::getProtocol());
        });
    }

    public function test_detect_ghostty(): void
    {
        $this->withEnv(['TERM_PROGRAM' => 'ghostty'], function () {
            Notify::reset();
            $this->assertSame('ghostty', Notify::getTerminal());
            $this->assertSame('osc777', Notify::getProtocol());
        });
    }

    public function test_detect_alacritty_no_support(): void
    {
        $this->withEnv(['ALACRITTY_WINDOW_ID' => '12345'], function () {
            Notify::reset();
            $this->assertSame('alacritty', Notify::getTerminal());
            $this->assertNull(Notify::getProtocol());
        });
    }

    public function test_detect_vte(): void
    {
        $this->withEnv([
            'VTE_VERSION' => '6800',
            'GHOSTTY_RESOURCES_DIR' => false,  // Clear to avoid conflict
            'TERM_PROGRAM' => false,  // Clear to avoid ghostty detection
        ], function () {
            Notify::reset();
            $this->assertSame('vte', Notify::getTerminal());
            $this->assertSame('osc777', Notify::getProtocol());
        });
    }

    public function test_unknown_terminal_returns_null(): void
    {
        // Clear all terminal-related env vars
        $this->withEnv([
            'KITTY_WINDOW_ID' => false,
            'ITERM_SESSION_ID' => false,
            'WEZTERM_PANE' => false,
            'WT_SESSION' => false,
            'ALACRITTY_WINDOW_ID' => false,
            'KONSOLE_VERSION' => false,
            'TERM_PROGRAM' => false,
            'VTE_VERSION' => false,
            'GHOSTTY_RESOURCES_DIR' => false,
        ], function () {
            Notify::reset();
            $this->assertNull(Notify::getTerminal());
            $this->assertNull(Notify::getProtocol());
        });
    }

    // =========================================================================
    // Force Protocol Tests
    // =========================================================================

    public function test_force_protocol_overrides_detection(): void
    {
        $this->withEnv(['KITTY_WINDOW_ID' => '1'], function () {
            Notify::reset();
            $this->assertSame('osc99', Notify::getProtocol());

            Notify::forceProtocol('osc777');
            $this->assertSame('osc777', Notify::getProtocol());

            Notify::forceProtocol(null);
            $this->assertSame('osc99', Notify::getProtocol());
        });
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function test_empty_message(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send(''));

        $this->assertSame("\x1b]9;\x07", $output);
    }

    public function test_unicode_message(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send('Hello 世界 🚀'));

        $this->assertSame("\x1b]9;Hello 世界 🚀\x07", $output);
    }

    public function test_emoji_in_title(): void
    {
        Notify::forceProtocol('osc777');
        $output = $this->captureOutput(fn () => Notify::send('Body', '✓ Success'));

        $this->assertSame("\x1b]777;notify;✓ Success;Body\x07", $output);
    }

    public function test_long_message(): void
    {
        Notify::forceProtocol('osc9');
        $longMessage = str_repeat('a', 1000);
        $output = $this->captureOutput(fn () => Notify::send($longMessage));

        $this->assertSame("\x1b]9;{$longMessage}\x07", $output);
    }

    public function test_newlines_are_stripped(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send("Hello\nWorld"));

        $this->assertSame("\x1b]9;HelloWorld\x07", $output);
    }

    public function test_tabs_are_stripped(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::send("Hello\tWorld"));

        $this->assertSame("\x1b]9;HelloWorld\x07", $output);
    }

    // =========================================================================
    // Bell and Fallback Tests
    // =========================================================================

    public function test_bell_sends_bel_character(): void
    {
        $output = $this->captureOutput(fn () => Notify::bell());

        $this->assertSame("\x07", $output);
    }

    public function test_send_or_bell_uses_notification_when_available(): void
    {
        Notify::forceProtocol('osc9');
        $output = $this->captureOutput(fn () => Notify::sendOrBell('Hello'));

        $this->assertSame("\x1b]9;Hello\x07", $output);
    }

    public function test_send_or_bell_falls_back_to_bell(): void
    {
        Notify::forceProtocol(null);
        // Clear detection to force null protocol
        Notify::reset();
        // Disable external fallback so we test bell fallback specifically
        Notify::disableFallback();
        $this->withEnv([
            'KITTY_WINDOW_ID' => false,
            'ITERM_SESSION_ID' => false,
            'WEZTERM_PANE' => false,
            'WT_SESSION' => false,
            'ALACRITTY_WINDOW_ID' => false,
            'KONSOLE_VERSION' => false,
            'TERM_PROGRAM' => false,
            'VTE_VERSION' => false,
            'GHOSTTY_RESOURCES_DIR' => false,
        ], function () {
            Notify::reset();
            Notify::disableFallback();
            $output = $this->captureOutput(fn () => Notify::sendOrBell('Hello'));

            $this->assertSame("\x07", $output);
        });
    }

    // =========================================================================
    // Capabilities and Supported Terminals Tests
    // =========================================================================

    public function test_capabilities_returns_correct_info(): void
    {
        $this->withEnv(['KITTY_WINDOW_ID' => '1'], function () {
            Notify::reset();
            $caps = Notify::capabilities();

            $this->assertSame('kitty', $caps['terminal']);
            $this->assertSame('osc99', $caps['protocol']);
            $this->assertTrue($caps['supports_title']);
            $this->assertTrue($caps['supports_urgency']);
            $this->assertFalse($caps['in_multiplexer']);
        });
    }

    public function test_capabilities_for_osc9_terminal(): void
    {
        $this->withEnv(['ITERM_SESSION_ID' => 'w0t0p0:12345'], function () {
            Notify::reset();
            $caps = Notify::capabilities();

            $this->assertSame('iterm2', $caps['terminal']);
            $this->assertSame('osc9', $caps['protocol']);
            $this->assertFalse($caps['supports_title']);
            $this->assertFalse($caps['supports_urgency']);
        });
    }

    public function test_capabilities_in_multiplexer(): void
    {
        $this->withEnv(['TMUX' => '/tmp/tmux-1000/default,12345,0', 'KITTY_WINDOW_ID' => '1'], function () {
            Notify::reset();
            $caps = Notify::capabilities();

            $this->assertTrue($caps['in_multiplexer']);
        });
    }

    public function test_supported_terminals_list(): void
    {
        $terminals = Notify::supportedTerminals();

        $this->assertArrayHasKey('kitty', $terminals);
        $this->assertArrayHasKey('iterm2', $terminals);
        $this->assertArrayHasKey('wezterm', $terminals);
        $this->assertArrayHasKey('alacritty', $terminals);

        $this->assertSame('osc99', $terminals['kitty']);
        $this->assertSame('osc9', $terminals['iterm2']);
        $this->assertNull($terminals['alacritty']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Capture output from a notification call.
     */
    protected function captureOutput(callable $callback): string
    {
        $stream = fopen('php://memory', 'r+');
        Notify::setOutputStream($stream);

        $callback();

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return $output;
    }

    /**
     * Run a callback with temporary environment variables.
     */
    protected function withEnv(array $vars, callable $callback): void
    {
        $original = [];

        foreach ($vars as $key => $value) {
            $original[$key] = getenv($key);

            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }

        try {
            $callback();
        } finally {
            foreach ($original as $key => $value) {
                if ($value === false) {
                    putenv($key);
                } else {
                    putenv("{$key}={$value}");
                }
            }
        }
    }
}
