<?php

namespace Studio;

use Vimeo\Vimeo;

class VimeoClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $accessToken,
        private readonly ?Vimeo $sdk = null,
    ) {
    }

    private const LANGUAGE_MAP = [
        'es' => 'es',
        'en' => 'en',
        'it' => 'it',
        'fr' => 'fr',
        'ca' => 'ca',
        'pt' => 'pt',
    ];

    /** @return array<int, array{uri: string}> */
    public function getTextTracks(string $videoId): array
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $videoId . '/texttracks', [], 'GET');
        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            throw new \RuntimeException('Error en obtenir les pistes de text de Vimeo.');
        }
        $items = $response['body']['data'] ?? [];
        return array_map(fn(array $t) => ['uri' => (string) ($t['uri'] ?? '')], $items);
    }

    public function deleteTextTrack(string $uri): void
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $client->request($uri, [], 'DELETE');
    }

    public function uploadAndActivateTextTrack(
        string $videoId,
        string $filePath,
        string $lang,
        string $label,
    ): void {
        if (!array_key_exists($lang, self::LANGUAGE_MAP)) {
            throw new \RuntimeException("Codi d'idioma no reconegut: $lang");
        }
        $bcp47 = self::LANGUAGE_MAP[$lang];
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $trackUri = $client->uploadTexttrack(
            '/videos/' . $videoId . '/texttracks',
            $filePath,
            'captions',
            $bcp47,
        );
        $patchResponse = $client->request($trackUri, ['active' => true, 'language' => $bcp47, 'name' => $label], 'PATCH');
        $status = $patchResponse['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Error en activar la pista de text: $trackUri");
        }
    }

    public function getVideo(string $id): string
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $id, [], 'GET');

        $status = $response['status'] ?? 0;
        if ($status === 404) {
            throw new VimeoNotFoundException(
                'Aquest ID de Vimeo no s\'ha trobat al vostre compte. Comproveu la URL o l\'ID i torneu-ho a provar.'
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new VimeoNotFoundException(
                'No s\'han pogut obtenir les dades del vídeo de Vimeo. Comproveu l\'ID i torneu-ho a provar.'
            );
        }

        $title = $response['body']['name'] ?? null;
        if (!is_string($title) || $title === '') {
            throw new VimeoNotFoundException(
                'Vimeo ha retornat un vídeo sense títol. Comproveu l\'ID i torneu-ho a provar.'
            );
        }

        return $title;
    }
}
