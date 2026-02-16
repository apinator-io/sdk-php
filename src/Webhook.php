<?php

namespace Apinator;

use Apinator\Errors\ValidationException;

class Webhook
{
    /**
     * Verify a webhook signature.
     *
     * @param string $secret The webhook secret
     * @param array $headers HTTP headers (case-insensitive)
     * @param string $body Raw request body
     * @param int|null $maxAge Maximum age in seconds (optional)
     * @return bool True if signature is valid
     * @throws ValidationException If signature is missing or timestamp is expired
     */
    public static function verify(string $secret, array $headers, string $body, ?int $maxAge = null): bool
    {
        // Normalize headers to lowercase keys
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        // Get signature header
        if (!isset($normalizedHeaders['x-realtime-signature'])) {
            throw new ValidationException('Missing x-realtime-signature header');
        }

        $signatureHeader = $normalizedHeaders['x-realtime-signature'];

        // Strip 'sha256=' prefix if present
        $actual = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        // Get timestamp header
        if (!isset($normalizedHeaders['x-realtime-timestamp'])) {
            throw new ValidationException('Missing x-realtime-timestamp header');
        }

        $timestamp = $normalizedHeaders['x-realtime-timestamp'];

        // Check timestamp freshness if maxAge is provided
        if ($maxAge !== null) {
            $age = time() - (int)$timestamp;
            if ($age > $maxAge) {
                throw new ValidationException("Webhook timestamp too old: {$age}s (max {$maxAge}s)");
            }
        }

        // Compute expected signature
        $input = "{$timestamp}.{$body}";
        $expected = hash_hmac('sha256', $input, $secret);

        // Constant-time comparison
        return hash_equals($expected, $actual);
    }

    /**
     * Sign a webhook payload.
     *
     * @param string $secret The webhook secret
     * @param string $timestamp Unix timestamp as string
     * @param string $payload JSON payload
     * @return string HMAC-SHA256 signature (hex)
     */
    public static function sign(string $secret, string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }
}
