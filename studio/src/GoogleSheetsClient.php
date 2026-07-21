<?php

namespace Studio;

/**
 * Fetches raw Google Sheet rows via service-account auth.
 * Callers see plain row arrays only.
 */
class GoogleSheetsClient
{
    private const RANGE = 'Full 1!A:H';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SHEETS_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function __construct(
        private readonly string $serviceAccountJsonPath,
        private readonly string $spreadsheetId,
    ) {}

    /**
     * @return list<list<string>>
     */
    public function fetchRows(string $range = self::RANGE): array
    {
        $accessToken = $this->fetchAccessToken();
        $url = self::SHEETS_BASE . '/' . rawurlencode($this->spreadsheetId)
            . '/values/' . rawurlencode($range);

        $response = $this->httpGet($url, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);

        $status = $response['status'];
        $body = $response['body'];
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Google Sheets API error (HTTP ' . $status . ').');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Google Sheets API response.');
        }

        $values = $decoded['values'] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $rows = [];
        foreach ($values as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = array_map(static fn($c) => (string) $c, $row);
        }

        return $rows;
    }

    private function fetchAccessToken(): string
    {
        $raw = @file_get_contents($this->serviceAccountJsonPath);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Could not read Google Sheets service-account JSON.');
        }
        $creds = json_decode($raw, true);
        if (!is_array($creds)) {
            throw new \RuntimeException('Invalid Google Sheets service-account JSON.');
        }

        $clientEmail = (string) ($creds['client_email'] ?? '');
        $privateKey = (string) ($creds['private_key'] ?? '');
        $tokenUri = (string) ($creds['token_uri'] ?? self::TOKEN_URI);
        if ($clientEmail === '' || $privateKey === '') {
            throw new \RuntimeException('Google Sheets service-account JSON missing required fields.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header . '.' . $claim;
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new \RuntimeException('Could not load Google Sheets private key.');
        }
        $signature = '';
        if (!openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Could not sign Google Sheets JWT.');
        }
        $jwt = $unsigned . '.' . $this->base64UrlEncode($signature);

        $tokenResponse = $this->httpPostForm($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        if ($tokenResponse['status'] < 200 || $tokenResponse['status'] >= 300) {
            throw new \RuntimeException('Google Sheets auth failed (HTTP ' . $tokenResponse['status'] . ').');
        }
        $tokenBody = json_decode($tokenResponse['body'], true);
        $accessToken = is_array($tokenBody) ? (string) ($tokenBody['access_token'] ?? '') : '';
        if ($accessToken === '') {
            throw new \RuntimeException('Google Sheets auth response missing access_token.');
        }

        return $accessToken;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function httpGet(string $url, array $headers): array
    {
        return $this->curlRequest($url, $headers, null);
    }

    /**
     * @param array<string, string> $fields
     * @return array{status: int, body: string}
     */
    private function httpPostForm(string $url, array $fields): array
    {
        return $this->curlRequest($url, ['Content-Type: application/x-www-form-urlencoded'], http_build_query($fields));
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function curlRequest(string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Could not init HTTP client for Google Sheets.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($responseBody === false || $errno !== 0) {
            throw new \RuntimeException('Google Sheets network error: ' . $error);
        }

        return ['status' => $status, 'body' => (string) $responseBody];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
