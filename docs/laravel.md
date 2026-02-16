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
        $client->verifyWebhook(
            $request->headers->all(),
            $request->getContent(),
            maxAge: 300,
        );
    } catch (\Apinator\Errors\ValidationException $e) {
        return response('Invalid signature', 401);
    }

    $payload = $request->json()->all();

    // Process webhook events...

    return response('OK', 200);
});
```

## 6. Frontend Setup

In your Blade template or Vue/React app:

```html
<script type="module">
  import { RealtimeClient } from '@apinator/sdk';

  const client = new RealtimeClient({
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
