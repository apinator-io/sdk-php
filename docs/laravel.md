# Laravel Integration

Step-by-step guide for integrating Apinator with Laravel.

## 1. Install the SDK

```bash
composer require apinator/apinator-php
```

## 2. Configuration

Add your credentials to `.env`:

```env
APINATOR_APP_ID=your-app-id
APINATOR_KEY=your-app-key
APINATOR_SECRET=your-app-secret
APINATOR_CLUSTER=eu
# Signing secret of your webhook endpoint (console → webhooks), not the API secret
APINATOR_WEBHOOK_SECRET=whsec_...
```

Register the client as a singleton in `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

use Apinator\Apinator;

public function register(): void
{
    $this->app->singleton(Apinator::class, function () {
        return new Apinator(
            appId: config('services.apinator.app_id'),
            key: config('services.apinator.key'),
            secret: config('services.apinator.secret'),
            cluster: config('services.apinator.cluster'),
        );
    });
}
```

Add the config in `config/services.php`:

```php
'apinator' => [
    'app_id' => env('APINATOR_APP_ID'),
    'key' => env('APINATOR_KEY'),
    'secret' => env('APINATOR_SECRET'),
    'cluster' => env('APINATOR_CLUSTER', 'eu'),
    'webhook_secret' => env('APINATOR_WEBHOOK_SECRET'),
],
```

## 3. Auth Endpoint

Create a route for channel authentication:

```php
// routes/api.php

use Illuminate\Http\Request;
use Apinator\Apinator;

Route::post('/realtime/auth', function (Request $request, Apinator $client) {
    $request->validate([
        'socket_id' => 'required|string',
        'channel_name' => 'required|string',
    ]);

    $channelName = $request->input('channel_name');

    // For presence channels, include user data
    $channelData = null;
    if (str_starts_with($channelName, 'presence-')) {
        $user = $request->user();
        $channelData = json_encode([
            'user_id' => (string) $user->id,
            'user_info' => [
                'name' => $user->name,
                'avatar' => $user->avatar_url,
            ],
        ]);
    }

    $auth = $client->authenticateChannel(
        $request->input('socket_id'),
        $channelName,
        $channelData,
    );

    return response()->json($auth);
})->middleware('auth:sanctum');
```

## 4. Triggering Events

From a controller or job:

```php
use Apinator\Apinator;

class MessageController extends Controller
{
    public function store(Request $request, Apinator $client)
    {
        $message = Message::create($request->validated());

        $client->trigger(
            name: 'new-message',
            data: json_encode([
                'id' => $message->id,
                'text' => $message->text,
                'user' => $message->user->name,
            ]),
            channel: 'chat-room-' . $message->room_id,
        );

        return response()->json($message, 201);
    }
}
```

## 5. Webhook Handling

```php
// routes/api.php

Route::post('/webhooks/apinator', function (Request $request, Apinator $client) {
    try {
        // false = signature mismatch; exception = missing header or stale timestamp
        $valid = $client->verifyWebhook(
            config('services.apinator.webhook_secret'), // whsec_..., from APINATOR_WEBHOOK_SECRET
            $request->headers->all(),                   // array values are fine
            $request->getContent(),
            maxAge: 300,
        );
    } catch (\Apinator\Errors\ValidationException $e) {
        $valid = false;
    }

    if (!$valid) {
        return response('Invalid signature', 401);
    }

    // ['id' => '...', 'event' => 'member_added', 'app_id' => '...',
    //  'channel' => 'presence-room', 'user_id' => '42', 'time_ms' => 1760000000000]
    $payload = $request->json()->all();

    // Deliveries are at-least-once: dedupe on the event id.
    if (!\Illuminate\Support\Facades\Cache::add('apinator-webhook:' . $payload['id'], true, now()->addDay())) {
        return response('OK', 200);
    }

    // Process webhook events...

    return response('OK', 200);
});
```

## 6. Frontend Setup

In your Blade template or Vue/React app:

```html
<script type="module">
  import { Apinator } from '@apinator/client';

  const client = new Apinator({
    appKey: '{{ config("services.apinator.key") }}',
    cluster: 'eu', // or 'us'
    authEndpoint: '/api/realtime/auth',
    authHeaders: {
      'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]')?.content,
    },
  });

  client.connect();
</script>
```
