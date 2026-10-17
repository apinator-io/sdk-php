<?php

/**
 * Webhook handler example.
 *
 * Receives and verifies webhook deliveries from Apinator.
 * Configure your webhook URL in the Apinator console and set
 * APINATOR_WEBHOOK_SECRET to that endpoint's signing secret (whsec_...).
 * It is not the app's API secret.
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
    appId: getenv('APINATOR_APP_ID') ?: 'your-app-id',
    key: getenv('APINATOR_KEY') ?: 'your-app-key',
    secret: getenv('APINATOR_SECRET') ?: 'your-app-secret',
    cluster: 'eu', // or 'us'
);

$webhookSecret = (string)getenv('APINATOR_WEBHOOK_SECRET');

$headers = getallheaders();
$body = file_get_contents('php://input');

// Verify the webhook signature. verifyWebhook() returns false on a signature
// mismatch and throws on a missing header or a timestamp older than 5 minutes
// (or more than 5 minutes in the future), so handle both.
try {
    $valid = $client->verifyWebhook($webhookSecret, $headers, $body, maxAge: 300);
} catch (ValidationException $e) {
    $valid = false;
}

if (!$valid) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

// Parse the payload:
// {"id":"<event uuid>","event":"member_added","app_id":"...","channel":"presence-room",
//  "user_id":"42","time_ms":1760000000000}
$payload = json_decode($body, true);

// Deliveries are at-least-once: skip events that were already processed.
// Use your database or cache in production; a temp file keeps this example
// self-contained.
$seenFile = sys_get_temp_dir() . '/apinator-webhook-' . sha1((string)($payload['id'] ?? '')) . '.seen';
if (file_exists($seenFile)) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}
touch($seenFile);

// Handle different event types
$eventType = $payload['event'] ?? '';
$channel = $payload['channel'] ?? '';

switch ($eventType) {
    case 'channel_occupied':
        // A channel just got its first subscriber
        error_log("Channel occupied: {$channel}");
        break;

    case 'channel_vacated':
        // A channel lost its last subscriber
        error_log("Channel vacated: {$channel}");
        break;

    case 'member_added':
        // A new member joined a presence channel
        error_log("Member added to {$channel}: " . ($payload['user_id'] ?? ''));
        break;

    case 'member_removed':
        // A member left a presence channel
        error_log("Member removed from {$channel}: " . ($payload['user_id'] ?? ''));
        break;

    case 'webhook.test':
        // Sent by the "test" button in the console
        error_log('Test webhook received');
        break;

    default:
        error_log("Unknown webhook event: {$eventType}");
}

// Respond with 200 to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
