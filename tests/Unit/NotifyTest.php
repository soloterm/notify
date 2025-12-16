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
    // Progress Bar Tests (OSC 9;4)
    // =========================================================================

    public function test_progress_normal(): void
    {
        // Use Ghostty which supports progress
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progress(50));

            $this->assertSame("\x1b]9;4;1;50\x07", $output);
        });
    }

    public function test_progress_with_state(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progress(75, Notify::PROGRESS_ERROR));

            $this->assertSame("\x1b]9;4;2;75\x07", $output);
        });
    }

    public function test_progress_clamps_to_100(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progress(150));

            $this->assertSame("\x1b]9;4;1;100\x07", $output);
        });
    }

    public function test_progress_clamps_to_0(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progress(-50));

            $this->assertSame("\x1b]9;4;1;0\x07", $output);
        });
    }

    public function test_progress_clear(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progressClear());

            $this->assertSame("\x1b]9;4;0;0\x07", $output);
        });
    }

    public function test_progress_error(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progressError(100));

            $this->assertSame("\x1b]9;4;2;100\x07", $output);
        });
    }

    public function test_progress_paused(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progressPaused(60));

            $this->assertSame("\x1b]9;4;4;60\x07", $output);
        });
    }

    public function test_progress_indeterminate(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progressIndeterminate());

            $this->assertSame("\x1b]9;4;3;0\x07", $output);
        });
    }

    public function test_progress_wraps_for_tmux(): void
    {
        $this->withEnv([
            'TMUX' => '/tmp/tmux-1000/default,12345,0',
            'GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty',
        ], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::progress(50));

            $this->assertSame("\x1bPtmux;\x1b\x1b]9;4;1;50\x07\x1b\\", $output);
        });
    }

    public function test_progress_returns_false_on_unsupported_terminal(): void
    {
        $this->withEnv([
            'KITTY_WINDOW_ID' => '1',  // Kitty doesn't support progress
            'GHOSTTY_RESOURCES_DIR' => false,
            'WT_SESSION' => false,
            'ITERM_SESSION_ID' => false,
            'TERM_PROGRAM' => false,
        ], function () {
            Notify::reset();
            $result = Notify::progress(50);

            $this->assertFalse($result);
        });
    }

    public function test_supports_progress_ghostty(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $this->assertTrue(Notify::supportsProgress());
        });
    }

    public function test_supports_progress_windows_terminal(): void
    {
        $this->withEnv(['WT_SESSION' => 'abc-123'], function () {
            Notify::reset();
            $this->assertTrue(Notify::supportsProgress());
        });
    }

    public function test_supports_progress_iterm2_366(): void
    {
        $this->withEnv([
            'ITERM_SESSION_ID' => 'w0t0p0:12345',
            'TERM_PROGRAM_VERSION' => '3.6.6',
        ], function () {
            Notify::reset();
            $this->assertTrue(Notify::supportsProgress());
        });
    }

    public function test_supports_progress_iterm2_367(): void
    {
        $this->withEnv([
            'ITERM_SESSION_ID' => 'w0t0p0:12345',
            'TERM_PROGRAM_VERSION' => '3.6.7',
        ], function () {
            Notify::reset();
            $this->assertTrue(Notify::supportsProgress());
        });
    }

    public function test_does_not_support_progress_iterm2_365(): void
    {
        $this->withEnv([
            'ITERM_SESSION_ID' => 'w0t0p0:12345',
            'TERM_PROGRAM_VERSION' => '3.6.5',
        ], function () {
            Notify::reset();
            $this->assertFalse(Notify::supportsProgress());
        });
    }

    public function test_does_not_support_progress_iterm2_no_version(): void
    {
        $this->withEnv([
            'ITERM_SESSION_ID' => 'w0t0p0:12345',
            'TERM_PROGRAM_VERSION' => false,
        ], function () {
            Notify::reset();
            $this->assertFalse(Notify::supportsProgress());
        });
    }

    public function test_does_not_support_progress_kitty(): void
    {
        $this->withEnv(['KITTY_WINDOW_ID' => '1'], function () {
            Notify::reset();
            $this->assertFalse(Notify::supportsProgress());
        });
    }

    public function test_does_not_support_progress_wezterm(): void
    {
        $this->withEnv(['WEZTERM_PANE' => '0'], function () {
            Notify::reset();
            $this->assertFalse(Notify::supportsProgress());
        });
    }

    public function test_capabilities_includes_supports_progress(): void
    {
        $this->withEnv(['GHOSTTY_RESOURCES_DIR' => '/usr/share/ghostty'], function () {
            Notify::reset();
            $caps = Notify::capabilities();

            $this->assertArrayHasKey('supports_progress', $caps);
            $this->assertTrue($caps['supports_progress']);
        });
    }

    public function test_capabilities_supports_progress_false_for_kitty(): void
    {
        $this->withEnv(['KITTY_WINDOW_ID' => '1'], function () {
            Notify::reset();
            $caps = Notify::capabilities();

            $this->assertArrayHasKey('supports_progress', $caps);
            $this->assertFalse($caps['supports_progress']);
        });
    }

    // =========================================================================
    // Request Attention Tests (OSC 1337)
    // =========================================================================

    public function test_request_attention(): void
    {
        $output = $this->captureOutput(fn () => Notify::requestAttention());

        $this->assertSame("\x1b]1337;RequestAttention=yes\x07", $output);
    }

    public function test_request_attention_with_fireworks(): void
    {
        $output = $this->captureOutput(fn () => Notify::requestAttention(true));

        $this->assertSame("\x1b]1337;RequestAttention=fireworks\x07", $output);
    }

    public function test_fireworks_helper(): void
    {
        $output = $this->captureOutput(fn () => Notify::fireworks());

        $this->assertSame("\x1b]1337;RequestAttention=fireworks\x07", $output);
    }

    public function test_steal_focus(): void
    {
        $output = $this->captureOutput(fn () => Notify::stealFocus());

        $this->assertSame("\x1b]1337;StealFocus\x07", $output);
    }

    public function test_request_attention_wraps_for_tmux(): void
    {
        $this->withEnv(['TMUX' => '/tmp/tmux-1000/default,12345,0'], function () {
            Notify::reset();
            $output = $this->captureOutput(fn () => Notify::requestAttention());

            $this->assertSame("\x1bPtmux;\x1b\x1b]1337;RequestAttention=yes\x07\x1b\\", $output);
        });
    }

    // =========================================================================
    // Hyperlink Tests (OSC 8)
    // =========================================================================

    public function test_hyperlink_basic(): void
    {
        $result = Notify::hyperlink('https://example.com', 'Click here');

        $this->assertSame("\x1b]8;;https://example.com\x07Click here\x1b]8;;\x07", $result);
    }

    public function test_hyperlink_without_text_uses_url(): void
    {
        $result = Notify::hyperlink('https://example.com');

        $this->assertSame("\x1b]8;;https://example.com\x07https://example.com\x1b]8;;\x07", $result);
    }

    public function test_hyperlink_with_id(): void
    {
        $result = Notify::hyperlink('https://example.com', 'Link', 'link1');

        $this->assertSame("\x1b]8;id=link1;https://example.com\x07Link\x1b]8;;\x07", $result);
    }

    public function test_hyperlink_empty_url_returns_text(): void
    {
        $result = Notify::hyperlink('', 'Plain text');

        $this->assertSame('Plain text', $result);
    }

    public function test_hyperlink_empty_url_and_text(): void
    {
        $result = Notify::hyperlink('');

        $this->assertSame('', $result);
    }

    // =========================================================================
    // Shell Integration Tests (OSC 133)
    // =========================================================================

    public function test_shell_prompt_start(): void
    {
        $output = $this->captureOutput(fn () => Notify::shellPromptStart());

        $this->assertSame("\x1b]133;A\x07", $output);
    }

    public function test_shell_command_start(): void
    {
        $output = $this->captureOutput(fn () => Notify::shellCommandStart());

        $this->assertSame("\x1b]133;B\x07", $output);
    }

    public function test_shell_command_executed(): void
    {
        $output = $this->captureOutput(fn () => Notify::shellCommandExecuted());

        $this->assertSame("\x1b]133;C\x07", $output);
    }

    public function test_shell_command_finished_with_exit_code(): void
    {
        $output = $this->captureOutput(fn () => Notify::shellCommandFinished(0));

        $this->assertSame("\x1b]133;D;0\x07", $output);
    }

    public function test_shell_command_finished_with_error_code(): void
    {
        $output = $this->captureOutput(fn () => Notify::shellCommandFinished(127));

        $this->assertSame("\x1b]133;D;127\x07", $output);
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
