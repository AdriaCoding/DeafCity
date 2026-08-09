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
        private SrtParser $srtParser,
        private object $translator,
        callable $logger,
        private CaptionFileIntegrityChecker $integrityChecker = new CaptionFileIntegrityChecker(),
        private SrtValidator $srtValidator = new SrtValidator(),
        private CaptionReader $reader = new CaptionReader(),
    ) {
        $this->logger = $logger;
    }

    /**
     * Run the translation job.
     *
     * @param string[] $targetLangs
     */
    public function run(string $masterPath, string $srcLang, array $targetLangs): void
    {
        $this->state->markRunning();
        $this->log("Starting translation {$srcLang}→" . implode(',', $targetLangs) . " master={$masterPath}");

        $parsed = $this->reader->read($masterPath);
        $cues = $parsed['cues'];

        if ($cues === []) {
            $this->log("No cues found in master captions — marking all languages as error");
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

                $integrityErrors = $this->integrityChecker->check($translatedCues);
                if ($integrityErrors !== []) {
                    throw new \RuntimeException(
                        'Translated captions failed integrity check: ' . $integrityErrors[0]['message']
                    );
                }

                $outPath = $this->jobManager->draftPathForLang($lang);
                $bytes = file_put_contents($outPath, $this->srtParser->write($translatedCues));
                if ($bytes === false) {
                    throw new \RuntimeException("Failed to write translated captions to {$outPath}");
                }

                try {
                    $this->srtValidator->validate($outPath, $outPath);
                } catch (\InvalidArgumentException $e) {
                    @unlink($outPath);
                    throw new \RuntimeException('Translated captions failed validation: ' . $e->getMessage());
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
