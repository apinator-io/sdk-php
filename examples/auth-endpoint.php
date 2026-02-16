<?php

/**
 * Channel authentication endpoint example.
 *
 * This is a standalone PHP script that handles channel auth requests.
 * For Laravel integration, see docs/laravel.md.
 *
 * The JS client SDK sends POST requests to this endpoint when subscribing
 * to private or presence channels.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Apinator\Apinator;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$client = new Apinator(
    appId: 'your-app-id',
    key: 'your-app-key',
    secret: 'your-app-secret',
    cluster: 'eu', // or 'us'
);

// Read the request
$input = json_decode(file_get_contents('php://input'), true);
$socketId = $input['socket_id'] ?? '';
$channelName = $input['channel_name'] ?? '';

if (empty($socketId) || empty($channelName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing socket_id or channel_name']);
    exit;
}

// TODO: Add your own authorization logic here.
// Check if the current user is allowed to subscribe to this channel.
// For example:
//   $user = getAuthenticatedUser();
//   if (!$user->canAccess($channelName)) {
//       http_response_code(403);
//       exit;
//   }

// For presence channels, include user data
$channelData = null;
if (str_starts_with($channelName, 'presence-')) {
    // Replace with your actual user data
    $channelData = json_encode([
        'user_id' => '1',
        'user_info' => [
            'name' => 'Alice',
        ],
    ]);
}

$auth = $client->authenticateChannel($socketId, $channelName, $channelData);

header('Content-Type: application/json');
echo json_encode($auth);
