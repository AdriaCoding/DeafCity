<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\GeminiTranslationException;
use Studio\GeminiTranslator;
use Studio\JobManager;
use Studio\TranslationJobState;
use Studio\TranslationRunner;
use Studio\UploadedFile;
use Studio\VttParser;

class TranslationRunnerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private TranslationJobState $state;
    private VttParser $vttParser;
    private array $logLines = [];

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-runner-' . uniqid();
        mkdir($this->jobsDir, 0777, true);

        $this->jobManager = new JobManager($this->jobsDir);
        $this->state = new TranslationJobState($this->jobManager);
        $this->vttParser = new VttParser();
        $this->logLines = [];

        // Create a job with a master VTT
        $vttPath = $this->jobsDir . '/upload.vtt';
        file_put_contents($vttPath, "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHello\n\n00:00:04.000 --> 00:00:06.000\nWorld\n");
        $this->jobManager->create(
            [
                'vimeo_id' => '123456',
                'video_title' => 'Test',
                'sign_language' => 'lse',
                'edition' => 'test-2024',
                'subtitle_language' => 'en',
                'step' => 'translation',
            ],
            new UploadedFile($vttPath, 'draft.vtt')
        );

        $this->state->initiate(['fr', 'it']);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    private function makeStubTranslator(array $translationsByLang): GeminiTranslator
    {
        return new GeminiTranslator(
            apiKey: 'stub',
            httpCallable: function (string $url, array $payload) use (&$translationsByLang) {
                // We can't know the lang from payload easily here, so use a counter approach
                // The stub will be overridden via a separate approach
                return ['status' => 200, 'body' => '{}'];
            },
        );
    }

    private function makeCallableTranslator(callable $fn): object
    {
        // Return an anonymous class wrapping the callable, matching the translate() signature
        return new class($fn) {
            public function __construct(private $fn) {}
            public function translate(array $cues, string $srcLang, string $tgtLang): array
            {
                return ($this->fn)($cues, $srcLang, $tgtLang);
            }
        };
    }

    private function logger(): callable
    {
        return function (string $line): void {
            $this->logLines[] = $line;
        };
    }

    // ------------------------------------------------------------------ happy path

    public function test_happy_path_writes_vtt_per_lang_and_marks_done(): void
    {
        $translator = $this->makeCallableTranslator(function (array $cues, string $src, string $tgt): array {
            return $tgt === 'fr'
                ? ['Bonjour', 'Monde']
                : ['Ciao', 'Mondo'];
        });

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            vttParser: $this->vttParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterVttPath: $this->jobManager->draftVttPath(),
            srcLang: 'en',
            targetLangs: ['fr', 'it'],
        );

        // Check VTT files were written
        $frPath = $this->jobManager->draftVttPathForLang('fr');
        $itPath = $this->jobManager->draftVttPathForLang('it');
        $this->assertFileExists($frPath);
        $this->assertFileExists($itPath);

        // Check VTT content
        $frParsed = $this->vttParser->parse($frPath);
        $this->assertSame('Bonjour', $frParsed['cues'][0]['text']);
        $this->assertSame('Monde', $frParsed['cues'][1]['text']);

        $itParsed = $this->vttParser->parse($itPath);
        $this->assertSame('Ciao', $itParsed['cues'][0]['text']);
        $this->assertSame('Mondo', $itParsed['cues'][1]['text']);

        // State transitions
        $data = $this->state->read();
        $this->assertSame('done', $data['status']);
        $this->assertSame('done', $data['languages']['fr']['status']);
        $this->assertSame('done', $data['languages']['it']['status']);
    }

    public function test_happy_path_marks_running_then_each_lang_running_then_done(): void
    {
        $stateTransitions = [];

        $translator = $this->makeCallableTranslator(function (array $cues, string $src, string $tgt) use (&$stateTransitions): array {
            $data = $this->state->read();
            $stateTransitions[] = [
                'lang' => $tgt,
                'top' => $data['status'],
                'lang_status' => $data['languages'][$tgt]['status'] ?? null,
            ];
            return array_fill(0, count($cues), 'translated');
        });

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            vttParser: $this->vttParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterVttPath: $this->jobManager->draftVttPath(),
            srcLang: 'en',
            targetLangs: ['fr', 'it'],
        );

        // When translating, top-level should be 'running' and lang should be 'running'
        $this->assertSame('running', $stateTransitions[0]['top']);
        $this->assertSame('running', $stateTransitions[0]['lang_status']);
        $this->assertSame('fr', $stateTransitions[0]['lang']);
    }

    // ------------------------------------------------------------------ per-language error

    public function test_one_language_error_leaves_others_done(): void
    {
        $translator = $this->makeCallableTranslator(function (array $cues, string $src, string $tgt): array {
            if ($tgt === 'fr') {
                throw new GeminiTranslationException('API quota exceeded');
            }
            return array_fill(0, count($cues), 'translated');
        });

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            vttParser: $this->vttParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterVttPath: $this->jobManager->draftVttPath(),
            srcLang: 'en',
            targetLangs: ['fr', 'it'],
        );

        $data = $this->state->read();
        $this->assertSame('done', $data['status']);
        $this->assertSame('error', $data['languages']['fr']['status']);
        $this->assertSame('done', $data['languages']['it']['status']);

        // Error message bubbles up
        $this->assertStringContainsString('API quota exceeded', $data['languages']['fr']['message']);
    }

    // ------------------------------------------------------------------ VTT round-trip

    public function test_vtt_timestamps_and_opaque_are_preserved(): void
    {
        // Write a VTT with opaque data
        $vttContent = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000 align:middle\nHello\n";
        file_put_contents($this->jobManager->draftVttPath(), $vttContent);

        $this->state->initiate(['ca']);

        $translator = $this->makeCallableTranslator(fn(array $cues) => ['Hola']);

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            vttParser: $this->vttParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterVttPath: $this->jobManager->draftVttPath(),
            srcLang: 'en',
            targetLangs: ['ca'],
        );

        $caPath = $this->jobManager->draftVttPathForLang('ca');
        $parsed = $this->vttParser->parse($caPath);

        $this->assertSame(1.0, $parsed['cues'][0]['start']);
        $this->assertSame(3.0, $parsed['cues'][0]['end']);
        $this->assertSame('align:middle', $parsed['cues'][0]['opaque']);
        $this->assertSame('Hola', $parsed['cues'][0]['text']);
    }

    // ------------------------------------------------------------------ logger called

    public function test_logger_is_called_for_each_language(): void
    {
        $translator = $this->makeCallableTranslator(fn(array $cues) => array_fill(0, count($cues), 'ok'));

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            vttParser: $this->vttParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterVttPath: $this->jobManager->draftVttPath(),
            srcLang: 'en',
            targetLangs: ['fr', 'it'],
        );

        $logText = implode("\n", $this->logLines);
        $this->assertStringContainsString('fr', $logText);
        $this->assertStringContainsString('it', $logText);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
