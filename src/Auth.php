<?php

namespace Apinator;

class Auth
{
    /**
     * Sign an API request.
     *
     * @param string $secret The API secret
     * @param string $method HTTP method (e.g., "POST", "GET")
     * @param string $path Request path (e.g., "/apps/123/events")
     * @param string $body Request body (empty string if no body)
     * @param int $timestamp Unix timestamp
     * @return string HMAC-SHA256 signature (hex)
     */
    public static function signRequest(string $secret, string $method, string $path, string $body, int $timestamp): string
    {
        // If body is empty, bodyMD5 is empty string (NOT md5 of empty string)
        $bodyMD5 = $body === '' ? '' : md5($body);

        $sigString = "{$timestamp}\n{$method}\n{$path}\n{$bodyMD5}";

        return hash_hmac('sha256', $sigString, $secret);
    }

    /**
     * Sign a channel authentication request.
     *
     * @param string $secret The API secret
     * @param string $socketId Socket ID from connection
     * @param string $channelName Channel name
     * @param string|null $channelData Optional channel data (for presence channels)
     * @return string HMAC-SHA256 signature (hex)
     */
    public static function signChannel(string $secret, string $socketId, string $channelName, ?string $channelData = null): string
    {
        $sigString = "{$socketId}:{$channelName}";

        if ($channelData !== null) {
            $sigString .= ":{$channelData}";
        }

        return hash_hmac('sha256', $sigString, $secret);
    }

    /**
     * Authenticate a channel subscription request.
     *
     * @param string $secret The API secret
     * @param string $key The API key
     * @param string $socketId Socket ID from connection
     * @param string $channelName Channel name
     * @param string|null $channelData Optional channel data (for presence channels)
     * @return array Authentication response with 'auth' and optionally 'channel_data'
     */
    public static function authenticateChannel(string $secret, string $key, string $socketId, string $channelName, ?string $channelData = null): array
    {
        $sig = self::signChannel($secret, $socketId, $channelName, $channelData);

        $result = ['auth' => "{$key}:{$sig}"];

        if ($channelData !== null) {
            $result['channel_data'] = $channelData;
        }

        return $result;
    }
}
