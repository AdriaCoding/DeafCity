<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\TranscriptionIntakeHandler;
use Studio\TranscriptionOrchestrator;
use Studio\TranslationJobState;

class TranscriptionIntakeHandlerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-tc-intake-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = "$dir/$e";
            is_dir($p) ? $this->removeDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    private function audioUpload(string $name = 'audio.mp3', int $error = UPLOAD_ERR_OK): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($tmp, "\xFF\xFB\x90\x00audio");
        return ['tmp_name' => $tmp, 'name' => $name, 'error' => $error, 'size' => 5];
    }

    private function handler(): TranscriptionIntakeHandler
    {
        return new TranscriptionIntakeHandler(
            studioConfig: $this->config,
            jobManager:   $this->jobManager,
        );
    }

    /**
     * @param array{result: string, message?: string} $orchestratorResult
     */
    private function handlerWithFakeOrchestrator(
        array $orchestratorResult,
        ?callable $captureCmd = null,
    ): TranscriptionIntakeHandler {
        $fakeOrchestrator = new class ($orchestratorResult) {
            private array $result;
            public function __construct(array $result)
            {
                $this->result = $result;
            }
            public function run(): array
            {
                return $this->result;
            }
        };

        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            'test-gemini-key',
            $captureCmd ?? function ($cmd) {},
        );

        return new TranscriptionIntakeHandler(
            studioConfig: $this->config,
            jobManager:   $this->jobManager,
            orchestrator: $fakeOrchestrator,
            launcher:     $launcher,
            translationState: new TranslationJobState($this->jobManager),
        );
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_rejects_when_no_audio_file_uploaded(): void
    {
        $result = $this->handler()->handlePost(['subtitle_language' => 'ca'], []);
        $this->assertArrayHasKey('intake_file', $result['errors']);
    }

    public function test_rejects_when_subtitle_language_is_empty(): void
    {
        $result = $this->handler()->handlePost([], ['intake_file' => $this->audioUpload()]);
        $this->assertArrayHasKey('subtitle_language', $result['errors']);
    }

    public function test_rejects_when_subtitle_language_not_in_config(): void
    {
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'zz'],
            ['intake_file' => $this->audioUpload()]
        );
        $this->assertArrayHasKey('subtitle_language', $result['errors']);
    }

    public function test_rejects_on_upload_error_code(): void
    {
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->audioUpload('a.mp3', UPLOAD_ERR_INI_SIZE)]
        );
        $this->assertArrayHasKey('intake_file', $result['errors']);
    }

    public function test_rejects_if_job_already_exists(): void
    {
        mkdir($this->jobsDir . '/current', 0777, true);
        file_put_contents($this->jobsDir . '/current/job.json', json_encode(['step' => 'x']));

        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->audioUpload()]
        );
        $this->assertArrayHasKey('_form', $result['errors']);
    }

    public function test_returns_values_echo_on_validation_failure(): void
    {
        $result = $this->handler()->handlePost(['subtitle_language' => 'ca'], []);
        $this->assertSame('ca', $result['values']['subtitle_language']);
    }

    // ── Job creation metadata ─────────────────────────────────────────────────

    public function test_creates_job_with_transcription_type(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(['result' => 'pipeline_transcribed']);
        $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload('interview.mp3')]);

        $this->assertSame('transcription', $this->jobManager->read()['job_type']);
    }

    public function test_creates_job_with_correct_subtitle_language(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(['result' => 'pipeline_transcribed']);
        $handler->handlePost(['subtitle_language' => 'es'], ['intake_file' => $this->audioUpload()]);

        $this->assertSame('es', $this->jobManager->read()['subtitle_language']);
    }

    public function test_creates_job_with_original_filename_stem(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(['result' => 'pipeline_transcribed']);
        $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload('interview_day1.mp3')]);

        $this->assertSame('interview_day1', $this->jobManager->read()['original_filename']);
    }

    public function test_original_filename_strips_extension_only(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(['result' => 'pipeline_transcribed']);
        $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload('My Recording.wav')]);

        $this->assertSame('My Recording', $this->jobManager->read()['original_filename']);
    }

    public function test_job_has_no_vimeo_id_or_edition(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(['result' => 'pipeline_transcribed']);
        $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload()]);

        $job = $this->jobManager->read();
        $this->assertArrayNotHasKey('vimeo_id', $job);
        $this->assertArrayNotHasKey('edition', $job);
        $this->assertArrayNotHasKey('sign_language', $job);
    }

    // ── Groq success path ────────────────────────────────────────────────────

    public function test_groq_success_spawns_translation_to_english(): void
    {
        $launched = null;
        $handler = $this->handlerWithFakeOrchestrator(
            ['result' => 'pipeline_transcribed'],
            function ($cmd) use (&$launched) { $launched = $cmd; },
        );

        $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload('talk.mp3')]);

        $this->assertNotNull($launched);
        $this->assertStringContainsString('run_translate.sh', $launched);
        $this->assertStringContainsString(escapeshellarg('en'), $launched);
    }

    public function test_groq_success_passes_source_lang_to_translation(): void
    {
        $launched = null;
        $handler = $this->handlerWithFakeOrchestrator(
            ['result' => 'pipeline_transcribed'],
            function ($cmd) use (&$launched) { $launched = $cmd; },
        );

        $handler->handlePost(['subtitle_language' => 'es'], ['intake_file' => $this->audioUpload()]);

        $this->assertStringContainsString(escapeshellarg('es'), $launched);
    }

    public function test_groq_success_initialises_translation_state_for_english(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(['result' => 'pipeline_transcribed']);
        $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload()]);

        $state = json_decode($this->jobManager->readTranslationState() ?? '{}', true);
        $this->assertArrayHasKey('en', $state['languages'] ?? []);
    }

    public function test_groq_success_returns_created_true(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(['result' => 'pipeline_transcribed']);
        $result = $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload()]);

        $this->assertTrue($result['created'] ?? false);
        $this->assertSame([], $result['errors']);
    }

    public function test_english_source_skips_translation_launch(): void
    {
        $launched = null;
        $handler = $this->handlerWithFakeOrchestrator(
            ['result' => 'pipeline_transcribed'],
            function ($cmd) use (&$launched) { $launched = $cmd; },
        );

        $handler->handlePost(['subtitle_language' => 'en'], ['intake_file' => $this->audioUpload()]);

        $this->assertNull($launched);
        $state = json_decode($this->jobManager->readTranslationState() ?? '{}', true);
        $this->assertSame('done', $state['status'] ?? null);
        $this->assertSame([], $state['languages'] ?? null);
    }

    // ── Local fallback path ──────────────────────────────────────────────────

    public function test_local_fallback_returns_created_without_extra_launch(): void
    {
        $launchCount = 0;
        $handler = $this->handlerWithFakeOrchestrator(
            ['result' => 'loading'],
            function ($cmd) use (&$launchCount) { $launchCount++; },
        );

        $result = $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload()]);

        $this->assertTrue($result['created'] ?? false);
        $this->assertSame(0, $launchCount, 'Pipeline script chains translation — handler must not spawn extra');
    }

    // ── Error path ───────────────────────────────────────────────────────────

    public function test_orchestrator_error_returns_form_error_message(): void
    {
        $handler = $this->handlerWithFakeOrchestrator(
            ['result' => 'error', 'message' => 'Format d\'àudio no reconegut.']
        );

        $result = $handler->handlePost(['subtitle_language' => 'ca'], ['intake_file' => $this->audioUpload()]);

        $this->assertArrayHasKey('_form', $result['errors']);
        $this->assertStringContainsString('Format d\'àudio no reconegut.', $result['errors']['_form']);
        $this->assertFalse($result['created'] ?? false);
    }
}
