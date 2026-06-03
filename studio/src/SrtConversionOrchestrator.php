<?php

namespace Studio;

/**
 * Spawns async SubRip → WebVTT conversion after intake.
 *
 * @phpstan-type Result array{result: 'loading'|'error', message?: string}
 */
class SrtConversionOrchestrator
{
    public function __construct(
        private readonly JobManager $jobManager,
        private readonly BackgroundJobLauncher $launcher,
    ) {
    }

    /** @return Result */
    public function run(): array
    {
        if (!$this->jobManager->exists()) {
            return ['result' => 'error', 'message' => 'No hi ha cap feina en curs.'];
        }

        if ($this->jobManager->hasDraftVtt()) {
            return ['result' => 'loading'];
        }

        $srtPath = $this->jobManager->srtSourcePath();
        if (!is_file($srtPath)) {
            $this->jobManager->cancel();
            return ['result' => 'error', 'message' => 'No s\'ha trobat el fitxer SubRip pujat.'];
        }

        file_put_contents(
            $this->jobManager->conversionStatusPath(),
            json_encode(['status' => 'pending'], JSON_UNESCAPED_UNICODE),
        );

        $this->launcher->launchSrtConversion(
            $srtPath,
            $this->jobManager->draftVttPath(),
            $this->jobManager->conversionStatusPath(),
        );

        return ['result' => 'loading'];
    }
}
