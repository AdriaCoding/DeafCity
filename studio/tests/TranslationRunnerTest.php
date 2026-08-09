<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\GeminiTranslationException;
use Studio\GeminiTranslator;
use Studio\JobManager;
use Studio\TranslationJobState;
use Studio\TranslationRunner;
use Studio\UploadedFile;
use Studio\SrtParser;

class TranslationRunnerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private TranslationJobState $state;
    private SrtParser $srtParser;
    private array $logLines = [];

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-runner-' . uniqid();
        mkdir($this->jobsDir, 0777, true);

        $this->jobManager = new JobManager($this->jobsDir);
        $this->state = new TranslationJobState($this->jobManager);
        $this->srtParser = new SrtParser();
        $this->logLines = [];

        // Create a job with a master VTT
        $vttPath = $this->jobsDir . '/upload.vtt';
        file_put_contents($vttPath, "1\n00:00:01,000 --> 00:00:03,000\nHello\n\n2\n00:00:04,000 --> 00:00:06,000\nWorld\n");
        $this->jobManager->createWithContent(
            [
                'vimeo_id' => '123456',
                'video_title' => 'Test',
                'sign_language' => 'lse',
                'edition' => 'test-2024',
                'subtitle_language' => 'en',
                'step' => 'translation',
            ],
            file_get_contents($vttPath)
        );

        $this->state->initiate(['fr', 'it'], 'en');
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

    public function test_happy_path_writes_captions_per_lang_and_marks_done(): void
    {
        $translator = $this->makeCallableTranslator(function (array $cues, string $src, string $tgt): array {
            return $tgt === 'fr'
                ? ['Bonjour', 'Monde']
                : ['Ciao', 'Mondo'];
        });

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            srtParser: $this->srtParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterPath: $this->jobManager->draftPath(),
            srcLang: 'en',
            targetLangs: ['fr', 'it'],
        );

        // Check VTT files were written
        $frPath = $this->jobManager->draftPathForLang('fr');
        $itPath = $this->jobManager->draftPathForLang('it');
        $this->assertFileExists($frPath);
        $this->assertFileExists($itPath);

        // Check VTT content
        $frParsed = $this->srtParser->parse($frPath);
        $this->assertSame('Bonjour', $frParsed['cues'][0]['text']);
        $this->assertSame('Monde', $frParsed['cues'][1]['text']);

        $itParsed = $this->srtParser->parse($itPath);
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
            srtParser: $this->srtParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterPath: $this->jobManager->draftPath(),
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
            srtParser: $this->srtParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterPath: $this->jobManager->draftPath(),
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

    // ------------------------------------------------------------------ integrity check

    public function test_overlapping_translated_cues_are_marked_error_not_written(): void
    {
        // Master has two cues that already overlap; translation preserves
        // timestamps exactly, so the defect propagates into the output —
        // the integrity check must catch it before markLanguageDone.
        $vttContent = "1\n00:00:01,000 --> 00:00:05,000\nHello\n\n2\n00:00:03,000 --> 00:00:06,000\nWorld\n";
        file_put_contents($this->jobManager->draftPath(), $vttContent);
        $this->state->initiate(['fr'], 'en');

        $translator = $this->makeCallableTranslator(fn(array $cues) => array_fill(0, count($cues), 'x'));

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            srtParser: $this->srtParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterPath: $this->jobManager->draftPath(),
            srcLang: 'en',
            targetLangs: ['fr'],
        );

        $data = $this->state->read();
        $this->assertSame('error', $data['languages']['fr']['status']);
        $this->assertStringContainsString('integrity check', $data['languages']['fr']['message']);
        $this->assertFileDoesNotExist($this->jobManager->draftPathForLang('fr'));
    }

    // ------------------------------------------------------------------ VTT round-trip

    /**
     * SubRip has no equivalent of WebVTT cue settings, so a master carrying
     * them keeps its timings across translation but loses the settings. No
     * production caption uses them (verified across all 288 by
     * verify_caption_conversion.php).
     */
    public function test_timestamps_survive_translation_and_cue_settings_are_dropped(): void
    {
        $vttContent = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000 align:middle\nHello\n";
        file_put_contents($this->jobManager->draftPath(), $vttContent);

        $this->state->initiate(['ca'], 'en');

        $translator = $this->makeCallableTranslator(fn(array $cues) => ['Hola']);

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            srtParser: $this->srtParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterPath: $this->jobManager->draftPath(),
            srcLang: 'en',
            targetLangs: ['ca'],
        );

        $caPath = $this->jobManager->draftPathForLang('ca');
        $parsed = $this->srtParser->parse($caPath);

        $this->assertSame(1.0, $parsed['cues'][0]['start']);
        $this->assertSame(3.0, $parsed['cues'][0]['end']);
        $this->assertSame('', $parsed['cues'][0]['opaque']);
        $this->assertSame('Hola', $parsed['cues'][0]['text']);
    }

    // ------------------------------------------------------------------ logger called

    public function test_logger_is_called_for_each_language(): void
    {
        $translator = $this->makeCallableTranslator(fn(array $cues) => array_fill(0, count($cues), 'ok'));

        $runner = new TranslationRunner(
            jobManager: $this->jobManager,
            state: $this->state,
            srtParser: $this->srtParser,
            translator: $translator,
            logger: $this->logger(),
        );

        $runner->run(
            masterPath: $this->jobManager->draftPath(),
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
