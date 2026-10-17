# API Reference

## Apinator

Main SDK entry point. Handles authenticated API requests, channel auth, and webhook verification.

### Constructor

```php
new Apinator(
    string $appId,
    string $key,
    string $secret,
    string $cluster,
)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$appId` | `string` | Your application ID |
| `$key` | `string` | Your API key |
| `$secret` | `string` | Your API secret |
| `$cluster` | `string` | Region cluster identifier (e.g. `"eu"`, `"us"`) |

### Methods

#### `trigger(string $name, string $data, ?string $channel, ?array $channels, ?string $socketId): void`

Trigger an event on one or more channels.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$name` | `string` | Yes | Event name |
| `$data` | `string` | Yes | JSON-encoded event data |
| `$channel` | `string\|null` | No* | Single channel name |
| `$channels` | `array\|null` | No* | Array of channel names |
| `$socketId` | `string\|null` | No | Socket ID to exclude from receiving the event |

*Either `$channel` or `$channels` must be provided.

**Throws:** `ValidationException` if neither channel nor channels is provided. `ApiException` on API failure.

#### `authenticateChannel(string $socketId, string $channelName, ?string $channelData): array`

Authenticate a channel subscription request.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$socketId` | `string` | Yes | Socket ID from the connection |
| `$channelName` | `string` | Yes | Channel name being subscribed to |
| `$channelData` | `string\|null` | No | JSON-encoded channel data (required for presence channels) |

**Returns:** `['auth' => 'key:signature']` (with optional `'channel_data'` for presence channels).

#### `getChannels(?string $prefix): array`

Get information about all channels.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$prefix` | `string\|null` | No | Filter channels by name prefix |

**Returns:** Array of channel information. **Throws:** `ApiException` on failure.

#### `getChannel(string $channelName): array`

Get information about a specific channel.

**Returns:** Channel information array. **Throws:** `ApiException` on failure.

#### `verifyWebhook(string $webhookSecret, array $headers, string $body, ?int $maxAge = null): bool`

Verify a webhook signature. Webhooks are signed with the webhook endpoint's own
signing secret (`whsec_...`, shown in the console per endpoint), not the app's API
secret.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$webhookSecret` | `string` | Yes | The webhook endpoint's signing secret (`whsec_...`) |
| `$headers` | `array` | Yes | HTTP headers (case-insensitive keys; string or array values, e.g. `$request->headers->all()`) |
| `$body` | `string` | Yes | Raw request body, exactly as received |
| `$maxAge` | `int\|null` | No | Maximum age in seconds (300 recommended). Also rejects timestamps more than 300 seconds in the future |

**Returns:** `true` if the signature is valid, **`false` if it does not match** —
always check the return value. **Throws:** `ValidationException` if
`$webhookSecret` is empty, a signature/timestamp header is missing, or the
timestamp is outside the allowed window.

The signature header is
`X-Realtime-Signature: sha256=hex(hmac_sha256($webhookSecret, "{timestamp}.{raw_body}"))`,
with the timestamp from `X-Realtime-Timestamp`. Deliveries also carry
`X-Realtime-Event` and `X-Realtime-Delivery-ID`. Payload:

```json
{"id":"<event uuid>","event":"member_added","app_id":"...","channel":"presence-room","user_id":"42","time_ms":1760000000000}
```

`event` is one of `channel_occupied`, `channel_vacated`, `member_added`,
`member_removed` (`user_id` only on the member events); the console's test sends
`webhook.test`. Deliveries are at-least-once, so dedupe on `id`.

---

## Auth

Static helper class for HMAC signing operations.

#### `Auth::signRequest(string $secret, string $method, string $path, string $body, int $timestamp): string`

Signs an API request. Returns hex-encoded HMAC-SHA256 signature.

#### `Auth::signChannel(string $secret, string $socketId, string $channelName, ?string $channelData): string`

Signs a channel authentication request. Returns hex-encoded HMAC-SHA256 signature.

#### `Auth::authenticateChannel(string $secret, string $key, string $socketId, string $channelName, ?string $channelData): array`

Full channel authentication. Returns `['auth' => 'key:sig']` with optional `channel_data`.

---

## Webhook

Static helper for webhook signature operations.

#### `Webhook::verify(string $secret, array $headers, string $body, ?int $maxAge = null): bool`

Verify a webhook signature using constant-time comparison. `$secret` is the
webhook endpoint's signing secret (`whsec_...`). Same return/throw behaviour as
`Apinator::verifyWebhook()`: `false` on a signature mismatch,
`ValidationException` on a missing header or a timestamp older than `$maxAge` or
more than 300 seconds (`Webhook::MAX_FUTURE_SKEW_SECONDS`) in the future.

#### `Webhook::sign(string $secret, string $timestamp, string $payload): string`

Generate a webhook signature. Useful for testing.

---

## Exceptions

All exceptions extend `Apinator\Errors\RealtimeException`.

| Exception | When |
|-----------|------|
| `ApiException` | API request failure (network error, 4xx/5xx response) |
| `AuthenticationException` | 401/403 response from the API |
| `ValidationException` | Invalid input or webhook verification failure |
