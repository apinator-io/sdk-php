# apinator/apinator-php

[![Packagist Version](https://img.shields.io/packagist/v/apinator/apinator-php.svg)](https://packagist.org/packages/apinator/apinator-php)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![CI](https://github.com/apinator-io/sdk-php/actions/workflows/test.yml/badge.svg)](https://github.com/apinator-io/sdk-php/actions/workflows/test.yml)

PHP server SDK for [Apinator](https://apinator.io) — trigger real-time events, authenticate channels, and verify webhooks.

## Features

- Trigger events on public, private, and presence channels
- Channel authentication (HMAC-SHA256)
- Webhook signature verification
- Channel introspection (list channels, get channel info)
- Zero external dependencies — PHP 8.1+ stdlib only
- Laravel integration guide included

## Installation

```bash
composer require apinator/apinator-php
```

## Quick Start

```php
use Apinator\Apinator;

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-app-secret',
    cluster: 'eu', // or 'us'
);

// Trigger an event
$client->trigger(
    name: 'new-message',
    data: json_encode(['text' => 'Hello!']),
    channel: 'chat-room',
);
```

## Cache Channels

Publish to a channel named `cache-`, `private-cache-` or `presence-cache-` and Apinator keeps
the last event for 30 minutes, so a client that subscribes after the publish still receives it.
Nothing else changes: trigger as usual, and authorize private and presence cache channels like
any other private or presence channel.

```php
$client->trigger(
    name: 'job-finished',
    data: json_encode(['status' => 'done']),
    channel: "private-cache-job-{$jobId}",
);
```

## Channel Authentication

For private and presence channels, your backend must provide an auth endpoint:

```php
use Apinator\Apinator;

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-app-secret',
    cluster: 'eu', // or 'us'
);

// In your auth route handler:
$socketId = $_POST['socket_id'];
$channelName = $_POST['channel_name'];

$auth = $client->authenticateChannel($socketId, $channelName);

header('Content-Type: application/json');
echo json_encode($auth);
```

For presence channels, include channel data:

```php
$channelData = json_encode([
    'user_id' => $currentUser->id,
    'user_info' => ['name' => $currentUser->name],
]);

$auth = $client->authenticateChannel($socketId, $channelName, $channelData);
```

## Webhook Verification

Each webhook endpoint you create in the console has its own signing secret
(`whsec_...`). Verify deliveries with that secret, not the app's API secret, and
pass the raw request body exactly as received.

`verifyWebhook()` **returns `false` on a signature mismatch** and throws
`ValidationException` for a missing header or a timestamp outside the window, so
handle both:

```php
use Apinator\Apinator;
use Apinator\Errors\ValidationException;

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-app-secret',
    cluster: 'eu', // or 'us'
);
$webhookSecret = getenv('APINATOR_WEBHOOK_SECRET'); // whsec_...

$headers = getallheaders();
$body = file_get_contents('php://input');

try {
    $valid = $client->verifyWebhook($webhookSecret, $headers, $body, maxAge: 300);
} catch (ValidationException $e) {
    $valid = false;
}

if (!$valid) {
    http_response_code(401);
    exit('Invalid webhook');
}

$payload = json_decode($body, true);
// ['id' => '<event uuid>', 'event' => 'member_added', 'app_id' => '...',
//  'channel' => 'presence-room', 'user_id' => '42', 'time_ms' => 1760000000000]
// user_id is only present on member_added / member_removed.

// Deliveries are at-least-once: skip $payload['id'] if already processed.
```

Each delivery carries `X-Realtime-Signature` (`sha256=<hex>`),
`X-Realtime-Timestamp` (unix seconds), `X-Realtime-Event` and
`X-Realtime-Delivery-ID`. Events: `channel_occupied`, `channel_vacated`,
`member_added`, `member_removed`; the console's test button sends `webhook.test`.
With `maxAge` set, timestamps older than `maxAge` seconds or more than 300 seconds
in the future are rejected. Header values may be strings or arrays (as from
Laravel's `$request->headers->all()`).

## Channel Introspection

```php
// List all channels
$channels = $client->getChannels();

// Filter by prefix
$presenceChannels = $client->getChannels(prefix: 'presence-');

// Get info about a specific channel
$info = $client->getChannel('presence-chat');
```

## API Reference

See [docs/api-reference.md](docs/api-reference.md) for the full API.

## Laravel Integration

See [docs/laravel.md](docs/laravel.md) for a step-by-step Laravel integration guide.

## Links

- [Installation Guide](docs/installation.md)
- [Quick Start Tutorial](docs/quickstart.md)
- [API Reference](docs/api-reference.md)
- [Laravel Integration](docs/laravel.md)
- [Architecture Guide](docs/architecture.md)
- [Contributing](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

## License

MIT — see [LICENSE](LICENSE).
