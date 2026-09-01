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

    public function test_launch_translation_passes_gemini_key_via_env_not_cmdline(): void
    {
        $captured = null;
        $capturedEnv = null;
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            'my-secret-key',
            function ($cmd) use (&$captured, &$capturedEnv) {
                $captured = $cmd;
                $capturedEnv = getenv('GEMINI_API_KEY');
            }
        );

        $launcher->launchTranslation('/master.vtt', '/status.json', 'es', '/jobdir', array('en', 'fr'));

        $this->assertNotNull($captured);
        // The secret must never appear on the command line — that's what a
        // local user could read via `ps` while the shell PHP's exec()
        // spawns (`/bin/sh -c "$cmd"`) is alive. It's made available to the
        // launched script only through the process environment instead.
        $this->assertStringNotContainsString('my-secret-key', $captured);
        $this->assertStringNotContainsString('GEMINI_API_KEY', $captured);
        $this->assertSame('my-secret-key', $capturedEnv);
        $this->assertStringContainsString('run_translate.sh', $captured);
        $this->assertStringContainsString(escapeshellarg('/master.vtt'), $captured);
        $this->assertStringContainsString(escapeshellarg('en,fr'), $captured);
        $this->assertStringContainsString('> /dev/null 2>&1 &', $captured);
        $this->assertStringStartsWith('nohup', $captured);
    }

    public function test_launch_translation_with_empty_gemini_key(): void
    {
        $captured = null;
        $capturedEnv = null;
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            '',
            function ($cmd) use (&$captured, &$capturedEnv) {
                $captured = $cmd;
                $capturedEnv = getenv('GEMINI_API_KEY');
            }
        );

        $launcher->launchTranslation('/master.vtt', '/status.json', 'es', '/jobdir', array('en'));

        $this->assertStringStartsWith('nohup', $captured);
        $this->assertSame('', $capturedEnv);
    }

    public function test_gemini_key_env_var_is_restored_after_launch(): void
    {
        $previous = getenv('GEMINI_API_KEY');
        putenv('GEMINI_API_KEY=pre-existing-value');

        try {
            $launcher = new BackgroundJobLauncher('/srv/scripts', 'my-secret-key', function ($cmd): void {
                // no-op: the assertion happens after the call returns
            });

            $launcher->launchTranslation('/master.vtt', '/status.json', 'es', '/jobdir', array('en'));

            // Must not leak the secret (or stay cleared) into whatever this
            // long-lived PHP-FPM worker process handles next.
            $this->assertSame('pre-existing-value', getenv('GEMINI_API_KEY'));
        } finally {
            if ($previous === false) {
                putenv('GEMINI_API_KEY');
            } else {
                putenv('GEMINI_API_KEY=' . $previous);
            }
        }
    }

    public function test_launch_transcription_pipeline_calls_pipeline_script(): void
    {
        $captured = null;
        $capturedEnv = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'gemini-key', function ($cmd) use (&$captured, &$capturedEnv) {
            $captured = $cmd;
            $capturedEnv = getenv('GEMINI_API_KEY');
        });

        $launcher->launchTranscriptionPipeline(
            audioPath:            '/data/interview.mp3',
            draftOutputPath:        '/data/draft.vtt',
            statusPath:           '/data/transcription.json',
            revisionStatePath:    '/data/revision_status.json',
            translationStatePath: '/data/translation.json',
            jobDir:               '/data',
            sourceLang:           'ca',
            targetLang:           'en',
            model:                'whisper-large-v3-turbo',
        );

        $this->assertStringContainsString('run_transcription_pipeline.sh', $captured);
        $this->assertStringContainsString(escapeshellarg('/data/interview.mp3'), $captured);
        $this->assertStringContainsString(escapeshellarg('/data/revision_status.json'), $captured);
        $this->assertStringContainsString(escapeshellarg('ca'), $captured);
        $this->assertStringContainsString(escapeshellarg('en'), $captured);
        $this->assertStringNotContainsString('gemini-key', $captured);
        $this->assertSame('gemini-key', $capturedEnv);
        $this->assertStringContainsString('> /dev/null 2>&1 &', $captured);
    }

    public function test_launch_revision_and_translation_includes_gemini_key_and_paths(): void
    {
        $captured = null;
        $capturedEnv = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'my-secret-key', function ($cmd) use (&$captured, &$capturedEnv) {
            $captured = $cmd;
            $capturedEnv = getenv('GEMINI_API_KEY');
        });

        $launcher->launchRevisionAndTranslation(
            '/master.vtt',
            '/revision.json',
            '/translation.json',
            'ca',
            '/jobdir',
            ['en'],
        );

        $this->assertNotNull($captured);
        $this->assertStringNotContainsString('my-secret-key', $captured);
        $this->assertSame('my-secret-key', $capturedEnv);
        $this->assertStringContainsString('run_revise.sh', $captured);
        $this->assertStringContainsString(escapeshellarg('/master.vtt'), $captured);
        $this->assertStringContainsString(escapeshellarg('/revision.json'), $captured);
        $this->assertStringContainsString(escapeshellarg('/translation.json'), $captured);
        $this->assertStringContainsString(escapeshellarg('en'), $captured);
        $this->assertStringContainsString('> /dev/null 2>&1 &', $captured);
    }

    public function test_launch_revision_and_translation_defaults_translate_source_lang_to_source_lang(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'my-secret-key', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchRevisionAndTranslation(
            '/master.vtt',
            '/revision.json',
            '/translation.json',
            'ca',
            '/jobdir',
            ['en'],
        );

        $this->assertStringContainsString('--translate_source_lang ' . escapeshellarg('ca'), $captured);
        $this->assertStringContainsString('--dialect_name ' . escapeshellarg(''), $captured);
    }

    public function test_launch_revision_and_translation_forks_dialect_from_base_language(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'my-secret-key', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchRevisionAndTranslation(
            '/master.vtt',
            '/revision.json',
            '/translation.json',
            'es-mx',
            '/jobdir',
            ['en'],
            'es',
            'Mexican Spanish (not Peninsular Spanish)',
        );

        $this->assertStringContainsString('--source_lang ' . escapeshellarg('es-mx'), $captured);
        $this->assertStringContainsString('--translate_source_lang ' . escapeshellarg('es'), $captured);
        $this->assertStringContainsString('--dialect_name ' . escapeshellarg('Mexican Spanish (not Peninsular Spanish)'), $captured);
    }

    public function test_launch_transcription_passes_prompt_hint(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', '', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchTranscription('/audio.mp3', '/out.srt', '/status.json', 'es', 'whisper-large-v3-turbo', 'Mexican Spanish');

        $this->assertStringContainsString('--initial_prompt ' . escapeshellarg('Mexican Spanish'), $captured);
    }

    public function test_launch_transcription_prompt_hint_defaults_empty(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', '', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchTranscription('/audio.mp3', '/out.srt', '/status.json', 'es');

        $this->assertStringContainsString('--initial_prompt ' . escapeshellarg(''), $captured);
    }

    public function test_launch_transcription_pipeline_passes_prompt_hint_and_dialect_name(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'gemini-key', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchTranscriptionPipeline(
            audioPath:            '/data/interview.mp3',
            draftOutputPath:        '/data/draft.srt',
            statusPath:           '/data/transcription.json',
            revisionStatePath:    '/data/revision_status.json',
            translationStatePath: '/data/translation.json',
            jobDir:               '/data',
            sourceLang:           'es',
            targetLang:           'en',
            model:                'whisper-large-v3-turbo',
            promptHint:           'Mexican Spanish',
            dialectName:          'Mexican Spanish (not Peninsular Spanish)',
        );

        $this->assertStringContainsString('--initial_prompt ' . escapeshellarg('Mexican Spanish'), $captured);
        $this->assertStringContainsString('--dialect_name ' . escapeshellarg('Mexican Spanish (not Peninsular Spanish)'), $captured);
    }

    public function test_launch_batch_translate_passes_gemini_key_via_env_not_cmdline(): void
    {
        $captured = null;
        $capturedEnv = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'batch-secret', function ($cmd) use (&$captured, &$capturedEnv) {
            $captured = $cmd;
            $capturedEnv = getenv('GEMINI_API_KEY');
        });

        $launcher->launchBatchTranslate('/data/status.json');

        $this->assertStringContainsString('run_batch_translate.sh', $captured);
        $this->assertStringContainsString(escapeshellarg('/data/status.json'), $captured);
        $this->assertStringNotContainsString('batch-secret', $captured);
        $this->assertSame('batch-secret', $capturedEnv);
    }

    public function test_launch_sync_uses_push_script(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', '', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchSync('/data/sync-status.json');

        $this->assertStringContainsString('sync_to_vimeo.php', $captured);
        $this->assertStringContainsString(escapeshellarg('/data/sync-status.json'), $captured);
    }

    public function test_launch_shorten_bulk_queue_calls_shorten_bulk_script(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'gemini-key', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchShortenBulkQueue('/data');

        $this->assertStringContainsString('run_shorten_bulk.sh', $captured);
        $this->assertStringContainsString(escapeshellarg('/data'), $captured);
        $this->assertStringContainsString('> /dev/null 2>&1 &', $captured);
    }

    public function test_launch_transcription_pipeline_passes_translation_status_path(): void
    {
        $captured = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'k', function ($cmd) use (&$captured) {
            $captured = $cmd;
        });

        $launcher->launchTranscriptionPipeline(
            audioPath:            '/a.mp3',
            draftOutputPath:        '/d.vtt',
            statusPath:           '/t.json',
            revisionStatePath:    '/rev.json',
            translationStatePath: '/tr.json',
            jobDir:               '/dir',
            sourceLang:           'es',
            targetLang:           'en',
        );

        $this->assertStringContainsString(escapeshellarg('/tr.json'), $captured);
        $this->assertStringContainsString(escapeshellarg('/rev.json'), $captured);
    }
}
