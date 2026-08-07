<?php

declare(strict_types=1);

namespace OliveTin\Api;

/**
 * Minimal OliveTin Connect-RPC (JSON) client for session bootstrap and starting actions.
 *
 * Credentials are sent only as {@see https://datatracker.ietf.org/doc/html/rfc6750 Bearer}
 * tokens in the {@code Authorization} header. OliveTin treats this value as a JWT when JWT
 * authentication is configured; otherwise terminate TLS at a proxy that validates your API key
 * and forwards trusted identity headers OliveTin understands.
 */
final class OliveTinClient
{
    private const SERVICE_PATH = '/olivetin.api.v1.OliveTinApiService';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiPrefix = '/api',
    ) {
        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('API key must not be empty.');
        }
    }

    /**
     * Call {@code Init} (same bootstrap RPC the web UI uses). Useful to verify TLS, bearer auth,
     * and that the OliveTin API path is reachable.
     *
     * @return array<string, mixed> Decoded {@code InitResponse} (camelCase JSON keys per Connect)
     */
    public function init(): array
    {
        return $this->postUnary('Init', []);
    }

    /**
     * Start an action using its dashboard binding identifier.
     *
     * @param array<string, string> $arguments Argument name => value
     * @return array{executionTrackingId: string}
     */
    public function startAction(string $bindingId, array $arguments = [], ?string $uniqueTrackingId = null): array
    {
        $body = [
            'bindingId' => $bindingId,
            'arguments' => self::argumentsToWire($arguments),
        ];
        if ($uniqueTrackingId !== null && $uniqueTrackingId !== '') {
            $body['uniqueTrackingId'] = $uniqueTrackingId;
        }

        return $this->postUnary('StartAction', $body);
    }

    /**
     * Start an action by configured action {@code id} and wait until completion.
     *
     * @param array<string, string> $arguments
     * @return array<string, mixed> Decoded {@code LogEntry} message (camelCase keys)
     */
    public function startActionAndWait(string $actionId, array $arguments = []): array
    {
        $body = [
            'actionId' => $actionId,
            'arguments' => self::argumentsToWire($arguments),
        ];

        $response = $this->postUnary('StartActionAndWait', $body);
        if (!isset($response['logEntry']) || !is_array($response['logEntry'])) {
            throw new OliveTinApiException('Response missing logEntry', 200);
        }

        return $response['logEntry'];
    }

    /**
     * @param array<string, string> $arguments
     * @return list<array{name: string, value: string}>
     */
    private static function argumentsToWire(array $arguments): array
    {
        $out = [];
        foreach ($arguments as $name => $value) {
            $out[] = [
                'name' => (string) $name,
                'value' => (string) $value,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function postUnary(string $procedure, array $body): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . trim($this->apiPrefix, '/') . self::SERVICE_PATH . '/' . $procedure;

        $payloadJson = json_encode($body === [] ? new \stdClass() : $body, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Connect-Protocol-Version: 1',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new OliveTinApiException('curl_init failed', 0);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            unset($ch);
            throw new OliveTinApiException('cURL error: ' . $err, 0);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new OliveTinApiException('Invalid JSON in response', $status, previous: $e);
        }

        if ($decoded === null) {
            throw new OliveTinApiException('Empty JSON response', $status);
        }

        if ($status < 200 || $status >= 300) {
            throw OliveTinApiException::fromHttpResponse($status, $decoded, $raw);
        }

        return $decoded;
    }
}
