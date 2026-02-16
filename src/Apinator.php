<?php

namespace Apinator;

use Apinator\Errors\ApiException;
use Apinator\Errors\AuthenticationException;
use Apinator\Errors\ValidationException;

class Apinator
{
    private string $host;

    public function __construct(
        private string $appId,
        private string $key,
        private string $secret,
        private string $cluster,
    ) {
        $this->host = "https://ws-{$this->cluster}.apinator.io";
    }

    /**
     * Trigger an event on one or more channels.
     *
     * @param string $name Event name
     * @param string $data Event data (JSON string)
     * @param string|null $channel Single channel name
     * @param array|null $channels Multiple channel names
     * @param string|null $socketId Optional socket ID to exclude from receiving the event
     * @throws ValidationException If neither channel nor channels is provided
     * @throws ApiException If the API request fails
     */
    public function trigger(string $name, string $data, ?string $channel = null, ?array $channels = null, ?string $socketId = null): void
    {
        if ($channel === null && $channels === null) {
            throw new ValidationException('Either channel or channels must be provided');
        }

        $payload = [
            'name' => $name,
            'data' => $data,
        ];

        if ($channel !== null) {
            $payload['channel'] = $channel;
        }

        if ($channels !== null) {
            $payload['channels'] = $channels;
        }

        if ($socketId !== null) {
            $payload['socket_id'] = $socketId;
        }

        $path = "/apps/{$this->appId}/events";
        $body = json_encode($payload);

        $this->request('POST', $path, $body);
    }

    /**
     * Authenticate a channel subscription request.
     *
     * @param string $socketId Socket ID from connection
     * @param string $channelName Channel name
     * @param string|null $channelData Optional channel data (for presence channels)
     * @return array Authentication response with 'auth' and optionally 'channel_data'
     */
    public function authenticateChannel(string $socketId, string $channelName, ?string $channelData = null): array
    {
        return Auth::authenticateChannel($this->secret, $this->key, $socketId, $channelName, $channelData);
    }

    /**
     * Get information about all channels.
     *
     * @param string|null $filterByPrefix Filter channels by prefix
     * @return array Channel information
     * @throws ApiException If the API request fails
     */
    public function getChannels(?string $filterByPrefix = null): array
    {
        $path = "/apps/{$this->appId}/channels";

        if ($filterByPrefix !== null) {
            $path .= '?' . http_build_query(['filter_by_prefix' => $filterByPrefix]);
        }

        return $this->request('GET', $path, '');
    }

    /**
     * Get information about a specific channel.
     *
     * @param string $channelName Channel name
     * @return array Channel information
     * @throws ApiException If the API request fails
     */
    public function getChannel(string $channelName): array
    {
        $path = "/apps/{$this->appId}/channels/" . urlencode($channelName);

        return $this->request('GET', $path, '');
    }

    /**
     * Verify a webhook signature.
     *
     * @param array $headers HTTP headers
     * @param string $body Raw request body
     * @param int|null $maxAge Maximum age in seconds (optional)
     * @return bool True if signature is valid
     * @throws ValidationException If signature is invalid or timestamp is expired
     */
    public function verifyWebhook(array $headers, string $body, ?int $maxAge = null): bool
    {
        return Webhook::verify($this->secret, $headers, $body, $maxAge);
    }

    /**
     * Make an authenticated API request.
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param string $body Request body (empty string if no body)
     * @return array Decoded JSON response
     * @throws AuthenticationException If authentication fails
     * @throws ApiException If the API request fails
     */
    private function request(string $method, string $path, string $body): array
    {
        $timestamp = time();
        $canonicalPath = $this->canonicalPath($path);
        $signature = Auth::signRequest($this->secret, $method, $canonicalPath, $body, $timestamp);

        $url = rtrim($this->host, '/') . $path;

        $headers = [
            'Content-Type: application/json',
            'X-Realtime-Key: ' . $this->key,
            'X-Realtime-Timestamp: ' . $timestamp,
            'X-Realtime-Signature: ' . $signature,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new ApiException('Failed to connect to API', 0);
        }

        // Parse response headers
        $statusCode = 200;
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0] ?? '';
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
                $statusCode = (int)$matches[1];
            }
        }

        if ($statusCode >= 400) {
            $this->throwForStatus($statusCode, $response);
        }

        // Handle empty response (e.g., 204 No Content)
        if ($response === '') {
            return [];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                'Failed to decode JSON response: ' . json_last_error_msg(),
                $statusCode,
                $response
            );
        }

        return $decoded;
    }

    private function canonicalPath(string $path): string
    {
        return explode('?', $path, 2)[0];
    }

    private function throwForStatus(int $statusCode, string $response): void
    {
        $message = "API request failed with status {$statusCode}";
        $problem = json_decode($response, true);
        if (is_array($problem)) {
            if (isset($problem['detail']) && is_string($problem['detail']) && $problem['detail'] !== '') {
                $message = $problem['detail'];
            } elseif (isset($problem['title']) && is_string($problem['title']) && $problem['title'] !== '') {
                $message = $problem['title'];
            }
        }

        if ($statusCode === 401 || $statusCode === 403) {
            throw new AuthenticationException($message, $statusCode);
        }

        if ($statusCode === 400 || $statusCode === 422) {
            throw new ValidationException($message, $statusCode);
        }

        throw new ApiException($message, $statusCode, $response);
    }
}
