<?php

namespace Apinator;

use Apinator\Errors\ValidationException;

class Webhook
{
    /**
     * How far in the future (seconds) a webhook timestamp may be before it is
     * rejected, when $maxAge is given. Allows for clock skew.
     */
    public const MAX_FUTURE_SKEW_SECONDS = 300;

    /**
     * Verify a webhook signature.
     *
     * Apinator signs each delivery with the webhook endpoint's own signing secret
     * (`whsec_...`, shown in the console per endpoint), not the app's API secret:
     * `X-Realtime-Signature: sha256=hex(hmac_sha256(secret, "{timestamp}.{raw_body}"))`.
     *
     * Header values may be strings or arrays of strings (as returned by
     * Laravel/Symfony `$request->headers->all()`); for arrays the first entry is used.
     *
     * Missing headers and stale timestamps throw; a signature mismatch returns
     * false, so always check the return value.
     *
     * @param string $secret The webhook endpoint's signing secret (`whsec_...`)
     * @param array<string, string|string[]> $headers HTTP headers (case-insensitive keys)
     * @param string $body Raw request body, exactly as received
     * @param int|null $maxAge Maximum age in seconds (300 recommended). When set,
     *                         timestamps older than $maxAge or more than 300 seconds
     *                         in the future are rejected.
     * @return bool True if the signature is valid, false if it does not match
     * @throws ValidationException If a header is missing or the timestamp is outside the allowed window
     */
    public static function verify(string $secret, array $headers, string $body, ?int $maxAge = null): bool
    {
        // Normalize headers to lowercase keys
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        // Get signature header
        $signatureHeader = self::headerValue($normalizedHeaders, 'x-realtime-signature');
        if ($signatureHeader === null) {
            throw new ValidationException('Missing x-realtime-signature header');
        }

        // Strip 'sha256=' prefix if present
        $actual = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        // Get timestamp header
        $timestamp = self::headerValue($normalizedHeaders, 'x-realtime-timestamp');
        if ($timestamp === null) {
            throw new ValidationException('Missing x-realtime-timestamp header');
        }

        // Check timestamp freshness if maxAge is provided
        if ($maxAge !== null) {
            $age = time() - (int)$timestamp;
            if ($age > $maxAge) {
                throw new ValidationException("Webhook timestamp too old: {$age}s (max {$maxAge}s)");
            }
            if (-$age > self::MAX_FUTURE_SKEW_SECONDS) {
                $ahead = -$age;
                throw new ValidationException(
                    "Webhook timestamp too far in the future: {$ahead}s (max " . self::MAX_FUTURE_SKEW_SECONDS . 's)'
                );
            }
        }

        // Compute expected signature
        $expected = self::sign($secret, $timestamp, $body);

        // Constant-time comparison
        return hash_equals($expected, $actual);
    }

    /**
     * Sign a webhook payload.
     *
     * @param string $secret The webhook endpoint's signing secret
     * @param string $timestamp Unix timestamp as string
     * @param string $payload JSON payload
     * @return string HMAC-SHA256 signature (hex)
     */
    public static function sign(string $secret, string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    /**
     * Read a header as a string. Array values (e.g. from Symfony's HeaderBag)
     * use their first scalar entry. Returns null if the header is absent.
     *
     * @param array<string, mixed> $headers Headers with lowercase keys
     */
    private static function headerValue(array $headers, string $name): ?string
    {
        $value = $headers[$name] ?? null;

        if (is_array($value)) {
            $first = null;
            foreach ($value as $entry) {
                if (is_scalar($entry)) {
                    $first = $entry;
                    break;
                }
            }
            $value = $first;
        }

        return is_scalar($value) ? (string)$value : null;
    }
}
