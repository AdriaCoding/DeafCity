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
                'That Vimeo ID was not found on your account. Check the URL or ID and try again.'
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new VimeoNotFoundException(
                'Could not fetch video details from Vimeo. Check the ID and try again.'
            );
        }

        $title = $response['body']['name'] ?? null;
        if (!is_string($title) || $title === '') {
            throw new VimeoNotFoundException(
                'Vimeo returned a video without a title. Check the ID and try again.'
            );
        }

        return $title;
    }
}
