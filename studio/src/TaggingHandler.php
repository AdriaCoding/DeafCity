<?php

namespace Studio;

class TaggingHandler
{
    public function __construct(private JobManager $jobManager) {}

    /** @return array{ok: bool, errors: string[]} */
    public function handle(array $postData): array
    {
        $raw = is_array($postData['tags'] ?? null) ? $postData['tags'] : [];
        $trimmed = array_filter(array_map('trim', $raw), fn(string $t) => $t !== '');
        $tags = array_values(array_unique($trimmed));

        if ($tags === []) {
            return ['ok' => false, 'errors' => ['Heu de seleccionar almenys una etiqueta.']];
        }

        $this->jobManager->update(['tags' => $tags, 'step' => 'publication']);

        return ['ok' => true, 'errors' => []];
    }
}
