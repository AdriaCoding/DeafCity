<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\AudioPreprocessor;
use Studio\BackgroundJobLauncher;
use Studio\GroqTranscriber;
use Studio\GroqTranscriptionException;
use Studio\JobManager;
use Studio\TranscriptionOrchestrator;
use Studio\VttParser;

class TranscriptionOrchestratorTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/orch_test_' . uniqid();
        mkdir($this->jobsDir, 0775, true);
        $this->jobManager = new JobManager($this->jobsDir);
        // Create a Job with audio metadata, mirroring JobManager::createWithAudio.
        mkdir($this->jobsDir . '/current', 0775, true);
        file_put_contents(
            $this->jobsDir . '/current/job.json',
            json_encode([
                'vimeo_id' => '123',
                'subtitle_language' => 'ca',
                'intake_mode' => 'generate',
                'interpreter_audio' => 'interpreter_audio.wav',
            ])
        );
        file_put_contents($this->jobsDir . '/current/interpreter_audio.wav', 'fake');
        file_put_contents($this->jobsDir . '/current/transcription.json', json_encode(['status' => 'pending']));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->jobsDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = "$dir/$e";
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }

    /**
     * @param callable $transcribe  receives (audioPath, model, language), returns cues or throws
     */
    private function makeOrchestrator(
        callable $transcribe,
        ?callable $onLaunch = null,
        string $pipelineTargetLang = '',
    ): TranscriptionOrchestrator {
        $groq = new GroqTranscriber(
            apiKey: 'test-key',
            baseUrl: 'https://api.groq.com/openai/v1',
            timeoutSeconds: 20,
            httpCallable: function () {
                throw new \LogicException('transport should not be hit; transcribe is faked at a higher level');
            },
        );

        // Wrap the real GroqTranscriber in a tiny subclass-free fake via the seam:
        // instead, inject a fake transcriber object implementing transcribe().
        $fakeGroq = new class ($transcribe) extends GroqTranscriber {
            /** @var callable */
            private $fn;
            public function __construct(callable $fn)
            {
                parent::__construct('k', 'https://x', 20, fn() => ['status' => 0, 'body' => '']);
                $this->fn = $fn;
            }
            public function transcribe(string $audioPath, string $model, string $language): array
            {
                return ($this->fn)($audioPath, $model, $language);
            }
        };

        $preprocessor = new AudioPreprocessor(function (string $cmd, array &$out, int &$code) {
            // Pretend ffmpeg succeeded and produced the flac.
            $code = 0;
        });

        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            '',
            $onLaunch ?? function ($cmd) {
            }
        );

        return new TranscriptionOrchestrator(
            jobManager: $this->jobManager,
            groqTranscriber: $fakeGroq,
            audioPreprocessor: $preprocessor,
            launcher: $launcher,
            vttParser: new VttParser(),
            groqApiKey: 'test-key',
            groqModel: 'whisper-large-v3-turbo',
            localModel: 'whisper-large-v3-turbo',
            logger: function (string $line) {
            },
            clock: fn() => 0.0,
            pipelineTargetLang: $pipelineTargetLang,
        );
    }

    public function test_success_writes_draft_vtt_stamps_engine_returns_editor(): void
    {
        $orch = $this->makeOrchestrator(
            fn() => [
                ['start' => 0.0, 'end' => 1.5, 'text' => 'Hola', 'opaque' => ''],
            ],
        );

        $result = $orch->run();

        $this->assertSame('editor', $result['result']);
        $this->assertTrue($this->jobManager->hasDraftVtt());
        $vtt = file_get_contents($this->jobManager->draftVttPath());
        $this->assertStringContainsString('Hola', $vtt);
        $this->assertStringContainsString('WEBVTT', $vtt);
        $job = $this->jobManager->read();
        $this->assertSame('groq:whisper-large-v3-turbo', $job['transcription_engine']);
    }

    public function test_transport_failure_spawns_local_and_returns_loading(): void
    {
        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_TRANSPORT,
                'down'
            ),
            function ($cmd) use (&$launched) {
                $launched = $cmd;
            },
        );

        $result = $orch->run();

        $this->assertSame('loading', $result['result']);
        $this->assertNotNull($launched, 'local engine should be spawned');
        $this->assertStringContainsString('run_transcribe.sh', $launched);
        $this->assertStringContainsString('--model', $launched);
        $this->assertTrue($this->jobManager->exists(), 'Job must survive a fallback');
        $this->assertFalse($this->jobManager->hasDraftVtt());
    }

    public function test_empty_result_spawns_local_and_returns_loading(): void
    {
        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_EMPTY,
                'no cues'
            ),
            function ($cmd) use (&$launched) {
                $launched = $cmd;
            },
        );

        $result = $orch->run();

        $this->assertSame('loading', $result['result']);
        $this->assertNotNull($launched);
    }

    public function test_auth_failure_destroys_job_returns_error_no_spawn(): void
    {
        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_AUTH,
                '401'
            ),
            function ($cmd) use (&$launched) {
                $launched = $cmd;
            },
        );

        $result = $orch->run();

        $this->assertSame('error', $result['result']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotSame('', $result['message']);
        $this->assertNull($launched, 'auth failure must not spawn the local engine');
        $this->assertFalse($this->jobManager->exists(), 'Job must be destroyed on a loud failure');
    }

    public function test_bad_input_destroys_job_returns_error_no_spawn(): void
    {
        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_BAD_INPUT,
                '400'
            ),
            function ($cmd) use (&$launched) {
                $launched = $cmd;
            },
        );

        $result = $orch->run();

        $this->assertSame('error', $result['result']);
        $this->assertNull($launched);
        $this->assertFalse($this->jobManager->exists());
    }

    public function test_blank_key_goes_straight_to_local_without_calling_groq(): void
    {
        $launched = null;
        $groqCalled = false;
        $transcribe = function () use (&$groqCalled) {
            $groqCalled = true;
            return [];
        };

        // Rebuild with a blank key.
        $fakeGroq = new class ($transcribe) extends GroqTranscriber {
            /** @var callable */
            private $fn;
            public function __construct(callable $fn)
            {
                parent::__construct('k', 'https://x', 20, fn() => ['status' => 0, 'body' => '']);
                $this->fn = $fn;
            }
            public function transcribe(string $audioPath, string $model, string $language): array
            {
                return ($this->fn)($audioPath, $model, $language);
            }
        };

        $orch = new TranscriptionOrchestrator(
            jobManager: $this->jobManager,
            groqTranscriber: $fakeGroq,
            audioPreprocessor: new AudioPreprocessor(function (string $cmd, array &$out, int &$code) {
                $code = 0;
            }),
            launcher: new BackgroundJobLauncher('/srv/scripts', '', function ($cmd) use (&$launched) {
                $launched = $cmd;
            }),
            vttParser: new VttParser(),
            groqApiKey: '',
            groqModel: 'whisper-large-v3-turbo',
            localModel: 'whisper-large-v3-turbo',
            logger: function (string $line) {
            },
            clock: fn() => 0.0,
        );

        $result = $orch->run();

        $this->assertSame('loading', $result['result']);
        $this->assertFalse($groqCalled, 'blank key must skip Groq entirely');
        $this->assertNotNull($launched);
        $this->assertTrue($this->jobManager->exists());
    }

    public function test_pipeline_mode_groq_success_returns_pipeline_transcribed(): void
    {
        file_put_contents(
            $this->jobsDir . '/current/job.json',
            json_encode([
                'subtitle_language' => 'ca',
                'job_type'          => 'transcription',
                'intake_mode'       => 'generate',
                'interpreter_audio' => 'interpreter_audio.wav',
            ])
        );

        $orch = $this->makeOrchestrator(
            fn() => [['start' => 0.0, 'end' => 1.0, 'text' => 'Hola', 'opaque' => '']],
            pipelineTargetLang: 'en',
        );

        $result = $orch->run();

        $this->assertSame('pipeline_transcribed', $result['result']);
        $this->assertTrue($this->jobManager->hasDraftVtt());
    }

    public function test_normal_mode_groq_success_still_returns_editor(): void
    {
        $orch = $this->makeOrchestrator(
            fn() => [['start' => 0.0, 'end' => 1.0, 'text' => 'Hi', 'opaque' => '']],
        );
        $result = $orch->run();
        $this->assertSame('editor', $result['result']);
    }

    public function test_pipeline_mode_local_fallback_calls_pipeline_script_not_transcribe_script(): void
    {
        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_TRANSPORT, 'down'
            ),
            function ($cmd) use (&$launched) { $launched = $cmd; },
            pipelineTargetLang: 'en',
        );

        $result = $orch->run();

        $this->assertSame('loading', $result['result']);
        $this->assertNotNull($launched);
        $this->assertStringContainsString('run_transcription_pipeline.sh', $launched);
        $this->assertStringNotContainsString('run_transcribe.sh', $launched);
        $this->assertStringContainsString(escapeshellarg('en'), $launched);
    }

    public function test_pipeline_mode_english_source_uses_transcribe_script_only(): void
    {
        file_put_contents(
            $this->jobsDir . '/current/job.json',
            json_encode([
                'subtitle_language' => 'en',
                'job_type'          => 'transcription',
                'intake_mode'       => 'generate',
                'interpreter_audio' => 'interpreter_audio.wav',
            ])
        );

        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_TRANSPORT, 'down'
            ),
            function ($cmd) use (&$launched) { $launched = $cmd; },
            pipelineTargetLang: 'en',
        );

        $result = $orch->run();

        $this->assertSame('loading', $result['result']);
        $this->assertNotNull($launched);
        $this->assertStringContainsString('run_transcribe.sh', $launched);
        $this->assertStringNotContainsString('run_transcription_pipeline.sh', $launched);
    }

    public function test_pipeline_mode_auth_failure_still_destroys_job_no_spawn(): void
    {
        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_AUTH, '401'
            ),
            function ($cmd) use (&$launched) { $launched = $cmd; },
            pipelineTargetLang: 'en',
        );

        $result = $orch->run();

        $this->assertSame('error', $result['result']);
        $this->assertNull($launched);
        $this->assertFalse($this->jobManager->exists());
    }

    public function test_normal_mode_transport_failure_still_uses_transcribe_script(): void
    {
        $launched = null;
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_TRANSPORT, 'down'
            ),
            function ($cmd) use (&$launched) { $launched = $cmd; },
        );

        $result = $orch->run();

        $this->assertSame('loading', $result['result']);
        $this->assertStringContainsString('run_transcribe.sh', $launched);
        $this->assertStringNotContainsString('run_transcription_pipeline.sh', $launched);
    }

    public function test_logs_structured_line_with_engine_and_fallback(): void
    {
        $lines = [];
        $orch = $this->makeOrchestrator(
            fn() => throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_TRANSPORT,
                'down'
            ),
            function ($cmd) {
            },
        );
        // Re-wire the logger via a fresh orchestrator that captures lines.
        $fakeGroq = new class () extends GroqTranscriber {
            public function __construct()
            {
                parent::__construct('k', 'https://x', 20, fn() => ['status' => 0, 'body' => '']);
            }
            public function transcribe(string $audioPath, string $model, string $language): array
            {
                throw new GroqTranscriptionException(
                    GroqTranscriptionException::CATEGORY_TRANSPORT,
                    'down'
                );
            }
        };
        $orch = new TranscriptionOrchestrator(
            jobManager: $this->jobManager,
            groqTranscriber: $fakeGroq,
            audioPreprocessor: new AudioPreprocessor(function (string $cmd, array &$out, int &$code) {
                $code = 0;
            }),
            launcher: new BackgroundJobLauncher('/srv/scripts', '', function ($cmd) {
            }),
            vttParser: new VttParser(),
            groqApiKey: 'k',
            groqModel: 'whisper-large-v3-turbo',
            localModel: 'whisper-large-v3-turbo',
            logger: function (string $line) use (&$lines) {
                $lines[] = $line;
            },
            clock: fn() => 0.0,
        );

        $orch->run();

        $this->assertNotEmpty($lines);
        $joined = implode("\n", $lines);
        $this->assertStringContainsString('engine=local:whisper-large-v3-turbo', $joined);
        $this->assertStringContainsString('fallback=transport', $joined);
    }
}
