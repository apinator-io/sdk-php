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

```php
$headers = getallheaders();
$body = file_get_contents('php://input');

$client->verifyWebhook($headers, $body, maxAge: 300);
$payload = json_decode($body, true);
// Process the webhook payload...
```

## Next Steps

- See [API Reference](api-reference.md) for all available methods
- See [Laravel Integration](laravel.md) for Laravel-specific setup
