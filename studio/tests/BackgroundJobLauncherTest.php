<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;

class BackgroundJobLauncherTest extends TestCase
{
    public function test_launch_transcription_builds_nohup_command(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            '',
            function ($cmd) use (&$captured) {
                $captured = $cmd;
            }
        );

        $launcher->launchTranscription('/audio.mp3', '/out.vtt', '/status.json', 'es', 'whisper-large-v3-turbo');

        $this->assertNotNull($captured);
        $this->assertStringContainsString('nohup', $captured);
        $this->assertStringContainsString('run_transcribe.sh', $captured);
        $this->assertStringContainsString(escapeshellarg('/audio.mp3'), $captured);
        $this->assertStringContainsString(escapeshellarg('/out.vtt'), $captured);
        $this->assertStringContainsString(escapeshellarg('es'), $captured);
        $this->assertStringContainsString('--model ' . escapeshellarg('whisper-large-v3-turbo'), $captured);
        $this->assertStringContainsString('> /dev/null 2>&1 &', $captured);
    }

    public function test_launch_transcription_model_defaults(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            '',
            function ($cmd) use (&$captured) {
                $captured = $cmd;
            }
        );

        $launcher->launchTranscription('/audio.mp3', '/out.vtt', '/status.json', 'es');

        $this->assertStringContainsString('--model ' . escapeshellarg('whisper-large-v3-turbo'), $captured);
    }

    public function test_launch_transcription_escapes_paths_with_spaces(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            '',
            function ($cmd) use (&$captured) {
                $captured = $cmd;
            }
        );

        $launcher->launchTranscription('/path with spaces/audio.mp3', '/out.vtt', '/status.json', 'ca');

        $this->assertStringContainsString(escapeshellarg('/path with spaces/audio.mp3'), $captured);
    }

    public function test_launch_translation_includes_gemini_key_and_targets(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            'my-secret-key',
            function ($cmd) use (&$captured) {
                $captured = $cmd;
            }
        );

        $launcher->launchTranslation('/master.vtt', '/status.json', 'es', '/jobdir', array('en', 'fr'));

        $this->assertNotNull($captured);
        $this->assertStringContainsString(escapeshellarg('my-secret-key'), $captured);
        $this->assertStringContainsString('run_translate.sh', $captured);
        $this->assertStringContainsString(escapeshellarg('/master.vtt'), $captured);
        $this->assertStringContainsString(escapeshellarg('en,fr'), $captured);
        $this->assertStringContainsString('> /dev/null 2>&1 &', $captured);
    }

    public function test_launch_translation_with_empty_gemini_key(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            '',
            function ($cmd) use (&$captured) {
                $captured = $cmd;
            }
        );

        $launcher->launchTranslation('/master.vtt', '/status.json', 'es', '/jobdir', array('en'));

        $this->assertStringStartsWith('GEMINI_API_KEY=', $captured);
    }
}
