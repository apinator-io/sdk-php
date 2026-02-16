<?php

/**
 * Webhook handler example.
 *
 * Receives and verifies webhook deliveries from Apinator.
 * Configure your webhook URL in the Apinator dashboard.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Apinator\Apinator;
use Apinator\Errors\ValidationException;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-webhook-secret',
    cluster: 'eu', // or 'us'
);

$headers = getallheaders();
$body = file_get_contents('php://input');

// Verify the webhook signature (reject if older than 5 minutes)
try {
    $client->verifyWebhook($headers, $body, maxAge: 300);
} catch (ValidationException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

// Parse the payload
$payload = json_decode($body, true);

// Handle different event types
$eventType = $payload['event'] ?? '';

switch ($eventType) {
    case 'channel_occupied':
        // A channel just got its first subscriber
        error_log("Channel occupied: " . ($payload['channel'] ?? ''));
        break;

    case 'channel_vacated':
        // A channel lost its last subscriber
        error_log("Channel vacated: " . ($payload['channel'] ?? ''));
        break;

    case 'member_added':
        // A new member joined a presence channel
        error_log("Member added: " . json_encode($payload['member'] ?? []));
        break;

    case 'member_removed':
        // A member left a presence channel
        error_log("Member removed: " . json_encode($payload['member'] ?? []));
        break;

    default:
        error_log("Unknown webhook event: {$eventType}");
}

// Respond with 200 to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
