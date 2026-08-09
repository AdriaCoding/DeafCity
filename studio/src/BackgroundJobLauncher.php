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

    public function launchTranscription($audioPath, $draftOutputPath, $statusPath, $language, $model = 'whisper-large-v3-turbo', string $promptHint = '')
    {
        $cmd = sprintf(
            'nohup %s --audio_file %s --draft_output %s --status_file %s --language %s --model %s --initial_prompt %s > /dev/null 2>&1 &',
            escapeshellarg($this->scriptsDir . '/run_transcribe.sh'),
            escapeshellarg($audioPath),
            escapeshellarg($draftOutputPath),
            escapeshellarg($statusPath),
            escapeshellarg($language),
            escapeshellarg($model),
            escapeshellarg($promptHint)
        );
        call_user_func($this->exec, $cmd);
    }

    /** @param string $statusFilePath */
    public function launchSync($statusFilePath)
    {
        $script = $this->scriptsDir . '/sync_to_vimeo.php';
        $cmd = sprintf(
            'nohup php %s --status-file %s > /dev/null 2>&1 &',
            escapeshellarg($script),
            escapeshellarg($statusFilePath),
        );
        call_user_func($this->exec, $cmd);
    }

    public function launchTranscriptionPipeline(
        string $audioPath,
        string $draftOutputPath,
        string $statusPath,
        string $revisionStatePath,
        string $translationStatePath,
        string $jobDir,
        string $sourceLang,
        string $targetLang,
        string $model = 'whisper-large-v3-turbo',
        string $promptHint = '',
        string $dialectName = '',
    ): void {
        $cmd = sprintf(
            'GEMINI_API_KEY=%s nohup %s --audio_file %s --draft_output %s --status_file %s'
            . ' --revision_status %s --translation_status %s --job_dir %s --source_lang %s --target_lang %s --model %s'
            . ' --initial_prompt %s --dialect_name %s > /dev/null 2>&1 &',
            escapeshellarg($this->geminiApiKey),
            escapeshellarg($this->scriptsDir . '/run_transcription_pipeline.sh'),
            escapeshellarg($audioPath),
            escapeshellarg($draftOutputPath),
            escapeshellarg($statusPath),
            escapeshellarg($revisionStatePath),
            escapeshellarg($translationStatePath),
            escapeshellarg($jobDir),
            escapeshellarg($sourceLang),
            escapeshellarg($targetLang),
            escapeshellarg($model),
            escapeshellarg($promptHint),
            escapeshellarg($dialectName),
        );
        call_user_func($this->exec, $cmd);
    }

    /**
     * @param string $masterCaptionsPath
     * @param string $statusFilePath
     * @param string $sourceLang
     * @param string $jobDir
     * @param string[] $targetLangs
     */
    public function launchTranslation($masterCaptionsPath, $statusFilePath, $sourceLang, $jobDir, array $targetLangs)
    {
        $cmd = sprintf(
            'GEMINI_API_KEY=%s nohup %s --master_captions %s --status_file %s --source_lang %s --job_dir %s --target_langs %s > /dev/null 2>&1 &',
            escapeshellarg($this->geminiApiKey),
            escapeshellarg($this->scriptsDir . '/run_translate.sh'),
            escapeshellarg($masterCaptionsPath),
            escapeshellarg($statusFilePath),
            escapeshellarg($sourceLang),
            escapeshellarg($jobDir),
            escapeshellarg(implode(',', $targetLangs))
        );
        call_user_func($this->exec, $cmd);
    }

    /**
     * @param string $masterCaptionsPath
     * @param string $revisionStatusPath
     * @param string $translationStatusPath
     * @param string $sourceLang            passed to the revision prompt — may be a dialect id
     * @param string $jobDir
     * @param string[] $targetLangs
     * @param string $translateSourceLang   base language forwarded to translation; defaults to $sourceLang
     * @param string $dialectName           human-readable dialect name override for the revision prompt
     */
    public function launchRevisionAndTranslation(
        $masterCaptionsPath,
        $revisionStatusPath,
        $translationStatusPath,
        $sourceLang,
        $jobDir,
        array $targetLangs,
        string $translateSourceLang = '',
        string $dialectName = '',
    ): void {
        $targetLangsArg = implode(',', $targetLangs);
        $effectiveTranslateSourceLang = $translateSourceLang !== '' ? $translateSourceLang : $sourceLang;
        $cmd = sprintf(
            'GEMINI_API_KEY=%s nohup %s --draft_path %s --revision_status %s --source_lang %s --job_dir %s'
            . ' --translation_status %s --target_langs %s --translate_source_lang %s --dialect_name %s > /dev/null 2>&1 &',
            escapeshellarg($this->geminiApiKey),
            escapeshellarg($this->scriptsDir . '/run_revise.sh'),
            escapeshellarg($masterCaptionsPath),
            escapeshellarg($revisionStatusPath),
            escapeshellarg($sourceLang),
            escapeshellarg($jobDir),
            escapeshellarg($translationStatusPath),
            escapeshellarg($targetLangsArg),
            escapeshellarg($effectiveTranslateSourceLang),
            escapeshellarg($dialectName),
        );
        call_user_func($this->exec, $cmd);
    }

    /** @param string $statusFilePath */
    public function launchBatchTranslate($statusFilePath)
    {
        $cmd = sprintf(
            'GEMINI_API_KEY=%s nohup %s --status-file %s > /dev/null 2>&1 &',
            escapeshellarg($this->geminiApiKey),
            escapeshellarg($this->scriptsDir . '/run_batch_translate.sh'),
            escapeshellarg($statusFilePath),
        );
        call_user_func($this->exec, $cmd);
    }

    public function launchBulkQueue(string $dataDir): void
    {
        $cmd = sprintf(
            'nohup bash %s --data_dir %s > /dev/null 2>&1 &',
            escapeshellarg($this->scriptsDir . '/run_bulk.sh'),
            escapeshellarg($dataDir),
        );
        call_user_func($this->exec, $cmd);
    }

    public function launchShortenBulkQueue(string $dataDir): void
    {
        $cmd = sprintf(
            'nohup bash %s --data_dir %s > /dev/null 2>&1 &',
            escapeshellarg($this->scriptsDir . '/run_shorten_bulk.sh'),
            escapeshellarg($dataDir),
        );
        call_user_func($this->exec, $cmd);
    }
}
