<?php

namespace Studio;

class BackgroundJobLauncher
{
    /** @var string */
    private $scriptsDir;

    /** @var string */
    private $geminiApiKey;

    /** @var callable */
    private $exec;

    /**
     * @param string $scriptsDir
     * @param string $geminiApiKey
     * @param callable|null $exec
     */
    public function __construct($scriptsDir, $geminiApiKey = '', $exec = null)
    {
        $this->scriptsDir = $scriptsDir;
        $this->geminiApiKey = $geminiApiKey;
        $this->exec = $exec !== null ? $exec : function ($cmd) {
            exec($cmd);
        };
    }

    /**
     * @param string $audioPath
     * @param string $vttOutputPath
     * @param string $statusPath
     * @param string $language
     * @param string $model      faster-whisper model id (STUDIO_LOCAL_TRANSCRIBE_MODEL)
     */
    public function launchSrtConversion($srtPath, $vttOutputPath, $statusPath)
    {
        $cmd = sprintf(
            'nohup %s --srt_path %s --vtt_output %s --status_file %s > /dev/null 2>&1 &',
            escapeshellarg($this->scriptsDir . '/run_convert_srt.sh'),
            escapeshellarg($srtPath),
            escapeshellarg($vttOutputPath),
            escapeshellarg($statusPath),
        );
        call_user_func($this->exec, $cmd);
    }

    public function launchTranscription($audioPath, $vttOutputPath, $statusPath, $language, $model = 'whisper-large-v3-turbo')
    {
        $cmd = sprintf(
            'nohup %s --audio_file %s --vtt_output %s --status_file %s --language %s --model %s > /dev/null 2>&1 &',
            escapeshellarg($this->scriptsDir . '/run_transcribe.sh'),
            escapeshellarg($audioPath),
            escapeshellarg($vttOutputPath),
            escapeshellarg($statusPath),
            escapeshellarg($language),
            escapeshellarg($model)
        );
        call_user_func($this->exec, $cmd);
    }

    /** @param string $statusFilePath */
    public function launchSync($statusFilePath)
    {
        $script = $this->scriptsDir . '/sync_from_vimeo.php';
        $cmd = sprintf(
            'nohup php %s --status-file %s > /dev/null 2>&1 &',
            escapeshellarg($script),
            escapeshellarg($statusFilePath),
        );
        call_user_func($this->exec, $cmd);
    }

    /**
     * @param string $masterVttPath
     * @param string $statusFilePath
     * @param string $sourceLang
     * @param string $jobDir
     * @param string[] $targetLangs
     */
    public function launchTranslation($masterVttPath, $statusFilePath, $sourceLang, $jobDir, array $targetLangs)
    {
        $cmd = sprintf(
            'GEMINI_API_KEY=%s nohup %s --master_vtt %s --status_file %s --source_lang %s --job_dir %s --target_langs %s > /dev/null 2>&1 &',
            escapeshellarg($this->geminiApiKey),
            escapeshellarg($this->scriptsDir . '/run_translate.sh'),
            escapeshellarg($masterVttPath),
            escapeshellarg($statusFilePath),
            escapeshellarg($sourceLang),
            escapeshellarg($jobDir),
            escapeshellarg(implode(',', $targetLangs))
        );
        call_user_func($this->exec, $cmd);
    }
}
