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
