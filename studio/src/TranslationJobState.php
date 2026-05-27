<?php

namespace Studio;

class TranslationJobState
{
    public function __construct(private JobManager $jobManager) {}

    public function initiate(array $targetLangs): void
    {
        if ($targetLangs === []) {
            $this->write([
                'status' => 'done',
                'languages' => [],
            ]);
            return;
        }

        $languages = [];
        foreach ($targetLangs as $lang) {
            $languages[$lang] = ['status' => 'pending'];
        }

        $this->write([
            'status' => 'pending',
            'languages' => $languages,
        ]);
    }

    public function read(): array
    {
        $raw = $this->jobManager->readTranslationState();
        if ($raw === null) {
            return ['status' => 'pending', 'languages' => []];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : ['status' => 'pending', 'languages' => []];
    }

    public function getTopLevelStatus(): string
    {
        return $this->read()['status'] ?? 'pending';
    }

    public function getLanguageStatus(string $lang): array
    {
        $data = $this->read();
        $entry = $data['languages'][$lang] ?? null;
        if (!is_array($entry)) {
            return ['status' => 'pending'];
        }

        return $entry;
    }

    public function markRunning(): void
    {
        $data = $this->read();
        $data['status'] = 'running';
        $this->write($data);
    }

    public function markLanguageRunning(string $lang): void
    {
        $data = $this->read();
        $data['languages'][$lang] = ['status' => 'running'];
        $this->write($data);
    }

    public function markLanguageDone(string $lang): void
    {
        $data = $this->read();
        $data['languages'][$lang] = ['status' => 'done'];
        $data['status'] = $this->resolveTopLevelStatus($data['languages']);
        $this->write($data);
    }

    public function markLanguageError(string $lang, string $message): void
    {
        $data = $this->read();
        $data['languages'][$lang] = ['status' => 'error', 'message' => $message];
        $data['status'] = $this->resolveTopLevelStatus($data['languages']);
        $this->write($data);
    }

    public function markLanguageReviewed(string $lang): void
    {
        $data = $this->read();
        if (!isset($data['languages'][$lang])) {
            return;
        }
        $data['languages'][$lang] = ['status' => 'reviewed'];
        $this->write($data);
    }

    public function resetLanguage(string $lang): void
    {
        $data = $this->read();
        $data['languages'][$lang] = ['status' => 'pending'];
        $data['status'] = 'running';
        $this->write($data);
    }

    private function resolveTopLevelStatus(array $languages): string
    {
        if ($languages === []) {
            return 'pending';
        }

        foreach ($languages as $entry) {
            $status = is_array($entry) ? ($entry['status'] ?? 'pending') : 'pending';
            if ($status === 'pending') {
                return 'running';
            }
        }

        return 'done';
    }

    private function write(array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('No s\'ha pogut codificar l\'estat de traducció.');
        }

        $this->jobManager->writeTranslationState($encoded . "\n");
    }
}
