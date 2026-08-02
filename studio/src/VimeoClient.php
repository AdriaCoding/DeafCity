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
        string $vimeoCode,
        string $label,
    ): void {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $trackUri = $client->uploadTexttrack(
            '/videos/' . $videoId . '/texttracks',
            $filePath,
            'captions',
            $vimeoCode,
        );
        $patchResponse = $client->request($trackUri, ['active' => true, 'language' => $vimeoCode, 'name' => $label], 'PATCH');
        $status = $patchResponse['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Error en activar la pista de text: $trackUri");
        }
    }

    /** @return array<int, array{language: string, name: string, link: string}> */
    public function getTextTracksDetailed(string $videoId): array
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $videoId . '/texttracks', [], 'GET');
        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            throw new \RuntimeException('Error en obtenir les pistes de text de Vimeo.');
        }
        $items = $response['body']['data'] ?? [];
        $seen = [];
        $tracks = [];
        foreach ($items as $t) {
            $language = (string) ($t['language'] ?? '');
            $link = (string) ($t['link'] ?? '');
            if ($language === '' || $link === '') {
                continue;
            }
            // Skip Vimeo auto-generated captions
            if (str_contains($language, 'autogen') || str_contains((string) ($t['name'] ?? ''), 'auto_generated')) {
                continue;
            }
            // Deduplicate by language code — keep first occurrence
            if (isset($seen[$language])) {
                continue;
            }
            $seen[$language] = true;
            $tracks[] = [
                'language' => $language,
                'name' => (string) ($t['name'] ?? $language),
                'link' => $link,
            ];
        }
        return $tracks;
    }

    /** @return array<int, string> */
    public function getTagNames(string $videoId): array
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $videoId . '/tags', [], 'GET');
        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            return [];
        }
        $items = $response['body']['data'] ?? [];
        $tags = array_filter(array_map(fn(array $t): string => trim((string) ($t['tag'] ?? '')), $items));
        return array_values($tags);
    }

    public function getPlayerEmbedUrl(string $videoId): ?string
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $videoId . '?fields=player_embed_url', [], 'GET');
        $status = $response['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            return null;
        }
        $url = $response['body']['player_embed_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function getThumbnailUrl(string $id): ?string
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $id . '?fields=pictures', [], 'GET');
        $this->assertOkOrThrow($response);
        return $this->pickThumbnailUrl($response['body']['pictures']['sizes'] ?? []);
    }

    /**
     * Single fields-filtered GET for Catalog sheet sync.
     *
     * @return array{title: string, thumbnail_url: ?string, embed_url: ?string}
     */
    public function fetchVideoForCatalogSync(string $id, bool $needThumbnail, bool $needEmbed): array
    {
        $fields = ['name'];
        if ($needThumbnail) {
            $fields[] = 'pictures';
        }
        if ($needEmbed) {
            $fields[] = 'player_embed_url';
        }

        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $id . '?fields=' . implode(',', $fields), [], 'GET');
        $this->assertOkOrThrow($response);

        $title = $response['body']['name'] ?? null;
        if (!is_string($title) || $title === '') {
            throw new VimeoNotFoundException(
                'Vimeo ha retornat un vídeo sense títol. Comproveu l\'ID i torneu-ho a provar.'
            );
        }

        $thumbnailUrl = null;
        if ($needThumbnail) {
            $thumbnailUrl = $this->pickThumbnailUrl($response['body']['pictures']['sizes'] ?? []);
        }
        $embedUrl = null;
        if ($needEmbed) {
            $url = $response['body']['player_embed_url'] ?? null;
            $embedUrl = is_string($url) && $url !== '' ? $url : null;
        }

        return [
            'title' => $title,
            'thumbnail_url' => $thumbnailUrl,
            'embed_url' => $embedUrl,
        ];
    }

    /** Lightweight auth probe before bulk Catalog mutations. */
    public function assertAuthenticated(): void
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/me?fields=uri', [], 'GET');
        $this->assertOkOrThrow($response);
    }

    /**
     * @param list<array<string, mixed>>|mixed $sizes
     */
    private function pickThumbnailUrl(mixed $sizes): ?string
    {
        if (!is_array($sizes)) {
            return null;
        }
        // One step above 640px wide (typically 960x540); fall back to largest <= 640.
        $above640 = null;
        $above640Width = PHP_INT_MAX;
        $atOrBelow640 = null;
        $atOrBelow640Width = 0;
        foreach ($sizes as $size) {
            if (!is_array($size)) {
                continue;
            }
            $w = (int) ($size['width'] ?? 0);
            $link = $size['link'] ?? null;
            if (!is_string($link) || $link === '' || $w <= 0) {
                continue;
            }
            if ($w > 640 && $w < $above640Width) {
                $above640Width = $w;
                $above640 = $link;
            }
            if ($w <= 640 && $w >= $atOrBelow640Width) {
                $atOrBelow640Width = $w;
                $atOrBelow640 = $link;
            }
        }
        $chosen = $above640 ?? $atOrBelow640;
        if ($chosen === null && $sizes !== []) {
            $last = end($sizes);
            $chosen = is_array($last) ? ($last['link'] ?? null) : null;
        }
        return is_string($chosen) ? $chosen : null;
    }

    public function updateTitle(string $videoId, string $title): void
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $videoId, ['name' => $title], 'PATCH');
        $this->assertOkOrThrow($response);
    }

    /** @param string[] $tags */
    public function setTags(string $videoId, array $tags): void
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);

        $response = $client->request('/videos/' . $videoId . '/tags', [], 'GET');
        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            throw new \RuntimeException("Error en obtenir les etiquetes del vídeo $videoId.");
        }

        foreach ($response['body']['data'] ?? [] as $item) {
            $word = trim((string) ($item['tag'] ?? ''));
            if ($word !== '') {
                $client->request('/videos/' . $videoId . '/tags/' . rawurlencode($word), [], 'DELETE');
            }
        }

        foreach ($tags as $tag) {
            $word = trim((string) $tag);
            if ($word !== '') {
                $client->request('/videos/' . $videoId . '/tags/' . rawurlencode($word), [], 'PUT');
            }
        }
    }

    public function getVideo(string $id): string
    {
        $client = $this->sdk ?? new Vimeo($this->clientId, $this->clientSecret, $this->accessToken);
        $response = $client->request('/videos/' . $id . '?fields=name', [], 'GET');
        $this->assertOkOrThrow($response);

        $title = $response['body']['name'] ?? null;
        if (!is_string($title) || $title === '') {
            throw new VimeoNotFoundException(
                'Vimeo ha retornat un vídeo sense títol. Comproveu l\'ID i torneu-ho a provar.'
            );
        }

        return $title;
    }

    /**
     * @param array{status?: int|string, body?: mixed, headers?: array<string, mixed>} $response
     */
    private function assertOkOrThrow(array $response): void
    {
        $status = (int) ($response['status'] ?? 0);
        if ($status >= 200 && $status < 300) {
            return;
        }

        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $errorCode = isset($body['error_code']) ? (int) $body['error_code'] : null;
        $developerMessage = trim((string) ($body['developer_message'] ?? ''));
        $error = trim((string) ($body['error'] ?? ''));
        $detail = $developerMessage !== '' ? $developerMessage : $error;
        $headers = $this->normalizeHeaders(is_array($response['headers'] ?? null) ? $response['headers'] : []);

        if ($status === 429 || $errorCode === 9000) {
            $resetRaw = $headers['x-ratelimit-reset'] ?? null;
            throw new VimeoRateLimitedException(
                $this->formatApiError('Vimeo rate limit exceeded', $errorCode, $detail),
                self::parseRateLimitReset($resetRaw !== null ? (string) $resetRaw : null),
            );
        }

        if ($status === 401 || in_array($errorCode, [8000, 8001, 8003], true)) {
            throw new VimeoUnauthorizedException(
                $this->formatApiError('Vimeo authorization failed', $errorCode, $detail),
            );
        }

        if ($status === 403) {
            throw new VimeoForbiddenException(
                $this->formatApiError('Vimeo forbidden', $errorCode, $detail),
            );
        }

        if ($status === 404 || in_array($errorCode, [5000, 5033], true)) {
            throw new VimeoNotFoundException(
                $this->formatApiError('Vimeo video not found', $errorCode, $detail),
            );
        }

        if ($status === 0 || $status >= 500) {
            throw new VimeoTransientException(
                $this->formatApiError('Vimeo transient error (HTTP ' . $status . ')', $errorCode, $detail),
            );
        }

        throw new VimeoRequestException(
            $this->formatApiError('Vimeo request failed (HTTP ' . $status . ')', $errorCode, $detail),
        );
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            $out[strtolower((string) $key)] = is_array($value)
                ? (string) ($value[0] ?? '')
                : (string) $value;
        }
        return $out;
    }

    private function formatApiError(string $prefix, ?int $errorCode, string $detail): string
    {
        $parts = [$prefix];
        if ($errorCode !== null) {
            $parts[] = 'error_code=' . $errorCode;
        }
        if ($detail !== '') {
            $parts[] = $detail;
        }
        return implode(': ', $parts);
    }

    public static function parseRateLimitReset(?string $raw): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if (preg_match('/^\d+$/', $raw)) {
            $n = (int) $raw;
            // Heuristic: large values are unix timestamps; small are relative seconds.
            if ($n > 1_000_000_000) {
                return $n;
            }
            return time() + max(1, $n);
        }
        $parsed = strtotime($raw);
        return $parsed !== false ? $parsed : null;
    }
}
