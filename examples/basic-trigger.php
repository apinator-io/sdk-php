<?php

/**
 * Basic event triggering example.
 *
 * Usage: php examples/basic-trigger.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Apinator\Apinator;

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-app-secret',
    cluster: 'eu', // or 'us'
);

// Trigger on a single channel
$client->trigger(
    name: 'new-message',
    data: json_encode([
        'text' => 'Hello from PHP!',
        'timestamp' => time(),
    ]),
    channel: 'chat-room',
);

echo "Event triggered on chat-room\n";

// Trigger on multiple channels
$client->trigger(
    name: 'notification',
    data: json_encode(['message' => 'System update available']),
    channels: ['alerts-admin', 'alerts-users'],
);

echo "Event triggered on multiple channels\n";

// Trigger with socket exclusion (don't echo back to sender)
$client->trigger(
    name: 'typing',
    data: json_encode(['user' => 'alice']),
    channel: 'chat-room',
    socketId: '12345.67890', // exclude this socket
);

echo "Event triggered with socket exclusion\n";
