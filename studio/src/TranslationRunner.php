<?php

namespace Studio;

class TranslationRunner
{
    /** @var callable(string): void */
    private $logger;

    /**
     * $translator must have a translate(array $cues, string $srcLang, string $tgtLang): string[] method.
     * Using object type for flexibility (allows stub in tests without full GeminiTranslator).
     */
    public function __construct(
        private JobManager $jobManager,
        private TranslationJobState $state,
        private VttParser $vttParser,
        private object $translator,
        callable $logger,
    ) {
        $this->logger = $logger;
    }

    /**
     * Run the translation job.
     *
     * @param string[] $targetLangs
     */
    public function run(string $masterVttPath, string $srcLang, array $targetLangs): void
    {
        $this->state->markRunning();
        $this->log("Starting translation {$srcLang}→" . implode(',', $targetLangs) . " master={$masterVttPath}");

        $parsed = $this->vttParser->parse($masterVttPath);
        $cues = $parsed['cues'];

        if ($cues === []) {
            $this->log("No cues found in master VTT — marking all languages as error");
            foreach ($targetLangs as $lang) {
                $this->state->markLanguageError($lang, 'Error de traducció: no hi ha subtítols');
            }
            return;
        }

        foreach ($targetLangs as $lang) {
            $this->state->markLanguageRunning($lang);
            $n = count($cues);
            $this->log("Translating {$srcLang}→{$lang} ({$n} cues)");

            try {
                /** @var string[] $translations */
                $translations = $this->translator->translate(array_column($cues, 'text'), $srcLang, $lang);

                // Build output parsed structure with translated text
                $translatedCues = [];
                foreach ($cues as $i => $cue) {
                    $translatedCues[] = array_merge($cue, ['text' => $translations[$i] ?? '']);
                }

                $outParsed = [
                    'header' => $parsed['header'],
                    'opaque_blocks' => $parsed['opaque_blocks'],
                    'cues' => $translatedCues,
                ];

                $outPath = $this->jobManager->draftVttPathForLang($lang);
                $bytes = file_put_contents($outPath, $this->vttParser->write($outParsed));
                if ($bytes === false) {
                    throw new \RuntimeException("Failed to write translated VTT to {$outPath}");
                }

                $this->state->markLanguageDone($lang);
                $this->log("Translation complete {$srcLang}→{$lang} output={$outPath}");
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $this->state->markLanguageError($lang, $msg);
                $this->log("Translation failed {$srcLang}→{$lang}: {$msg}");
            }
        }
    }

    private function log(string $message): void
    {
        ($this->logger)(
            date('Y-m-d H:i:s') . ' [translate.php] ' . $message
        );
    }
}
