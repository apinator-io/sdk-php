# apinator/apinator-php

[![Packagist Version](https://img.shields.io/packagist/v/apinator/apinator-php.svg)](https://packagist.org/packages/apinator/apinator-php)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![CI](https://github.com/apinator/sdk-php/actions/workflows/test.yml/badge.svg)](https://github.com/apinator/sdk-php/actions/workflows/test.yml)

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

```php
use Apinator\Apinator;

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-webhook-secret',
    cluster: 'eu', // or 'us'
);

$headers = getallheaders();
$body = file_get_contents('php://input');

try {
    $client->verifyWebhook($headers, $body, maxAge: 300);
    // Webhook is valid — process the payload
    $payload = json_decode($body, true);
} catch (\Apinator\Errors\ValidationException $e) {
    http_response_code(401);
    echo 'Invalid webhook';
}
```

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
