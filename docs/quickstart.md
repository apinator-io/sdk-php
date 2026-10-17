# Quick Start

## 1. Install

```bash
composer require apinator/apinator-php
```

## 2. Create a Client

```php
use Apinator\Apinator;

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-app-secret',
    cluster: 'eu', // or 'us'
);
```

## 3. Trigger an Event

```php
$client->trigger(
    name: 'new-message',
    data: json_encode(['text' => 'Hello from PHP!']),
    channel: 'chat-room',
);
```

## 4. Authenticate Channels

Set up an auth endpoint for private/presence channels:

```php
// POST /api/realtime/auth
$socketId = $_POST['socket_id'];
$channelName = $_POST['channel_name'];

$auth = $client->authenticateChannel($socketId, $channelName);

header('Content-Type: application/json');
echo json_encode($auth);
```

## 5. Verify Webhooks

Use the webhook endpoint's signing secret (`whsec_...`, shown in the console per
endpoint), not the app's API secret. `verifyWebhook()` returns `false` on a bad
signature and throws `ValidationException` on a missing header or stale
timestamp:

```php
$headers = getallheaders();
$body = file_get_contents('php://input');

try {
    $valid = $client->verifyWebhook(getenv('APINATOR_WEBHOOK_SECRET'), $headers, $body, maxAge: 300);
} catch (\Apinator\Errors\ValidationException $e) {
    $valid = false;
}

if (!$valid) {
    http_response_code(401);
    exit;
}

$payload = json_decode($body, true);
// ['id' => '...', 'event' => 'member_added', 'channel' => 'presence-room', 'user_id' => '42', ...]
// Deliveries are at-least-once: skip $payload['id'] if already processed.
```

## Next Steps

- See [API Reference](api-reference.md) for all available methods
- See [Laravel Integration](laravel.md) for Laravel-specific setup
