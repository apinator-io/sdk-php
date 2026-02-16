<?php

namespace Apinator\Tests;

use PHPUnit\Framework\TestCase;
use Apinator\Errors\ValidationException;
use Apinator\Apinator;

class RealtimeClientTest extends TestCase
{
    private Apinator $client;

    protected function setUp(): void
    {
        $this->client = new Apinator(
            appId: 'test-app-id',
            key: 'test-key',
            secret: 'my-secret-key',
            cluster: 'eu',
        );
    }

    public function testAuthenticateChannel(): void
    {
        $result = $this->client->authenticateChannel(
            socketId: '12345.67890',
            channelName: 'private-chat'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayNotHasKey('channel_data', $result);

        // Verify auth format
        $this->assertStringStartsWith('test-key:', $result['auth']);

        // Extract signature
        $parts = explode(':', $result['auth'], 2);
        $this->assertCount(2, $parts);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $parts[1]);
    }

    public function testAuthenticateChannelWithChannelData(): void
    {
        $channelData = '{"user_id":"user1","name":"John Doe"}';

        $result = $this->client->authenticateChannel(
            socketId: '12345.67890',
            channelName: 'presence-chat',
            channelData: $channelData
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayHasKey('channel_data', $result);
        $this->assertSame($channelData, $result['channel_data']);

        // Verify auth format
        $this->assertStringStartsWith('test-key:', $result['auth']);
    }

    public function testAuthenticateChannelConsistency(): void
    {
        // Same parameters should produce same result
        $result1 = $this->client->authenticateChannel('socket1', 'private-test');
        $result2 = $this->client->authenticateChannel('socket1', 'private-test');

        $this->assertSame($result1['auth'], $result2['auth']);
    }

    public function testAuthenticateChannelDifferentSocketIds(): void
    {
        // Different socket IDs should produce different signatures
        $result1 = $this->client->authenticateChannel('socket1', 'private-test');
        $result2 = $this->client->authenticateChannel('socket2', 'private-test');

        $this->assertNotSame($result1['auth'], $result2['auth']);
    }

    public function testAuthenticateChannelDifferentChannelNames(): void
    {
        // Different channel names should produce different signatures
        $result1 = $this->client->authenticateChannel('socket1', 'private-channel1');
        $result2 = $this->client->authenticateChannel('socket1', 'private-channel2');

        $this->assertNotSame($result1['auth'], $result2['auth']);
    }

    public function testAuthenticateChannelWithAndWithoutChannelData(): void
    {
        // Same socket and channel but different channel data should produce different signatures
        $result1 = $this->client->authenticateChannel('socket1', 'presence-test');
        $result2 = $this->client->authenticateChannel('socket1', 'presence-test', '{"user_id":"1"}');

        $this->assertNotSame($result1['auth'], $result2['auth']);
        $this->assertArrayNotHasKey('channel_data', $result1);
        $this->assertArrayHasKey('channel_data', $result2);
    }

    public function testTriggerWithoutChannelThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Either channel or channels must be provided');

        $this->client->trigger(
            name: 'test-event',
            data: '{"message":"hello"}'
        );
    }

    public function testVerifyWebhookDelegates(): void
    {
        $timestamp = (string)time();
        $body = '{"event":"test"}';
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'my-secret-key');

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = $this->client->verifyWebhook($headers, $body);

        $this->assertTrue($result);
    }

    public function testVerifyWebhookWithInvalidSignature(): void
    {
        $timestamp = (string)time();
        $body = '{"event":"test"}';

        $headers = [
            'X-Realtime-Signature' => 'sha256=invalid',
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = $this->client->verifyWebhook($headers, $body);

        $this->assertFalse($result);
    }

    public function testClientInstantiation(): void
    {
        $client = new Apinator(
            appId: 'app-123',
            key: 'key-456',
            secret: 'secret-789',
            cluster: 'eu',
        );

        $this->assertInstanceOf(Apinator::class, $client);
    }

    public function testHostConstructionEU(): void
    {
        $client = new Apinator(
            appId: 'app-123',
            key: 'key-456',
            secret: 'secret-789',
            cluster: 'eu',
        );

        $host = (new \ReflectionProperty($client, 'host'))->getValue($client);
        $this->assertSame('https://ws-eu.apinator.io', $host);
    }

    public function testHostConstructionUS(): void
    {
        $client = new Apinator(
            appId: 'app-123',
            key: 'key-456',
            secret: 'secret-789',
            cluster: 'us',
        );

        $host = (new \ReflectionProperty($client, 'host'))->getValue($client);
        $this->assertSame('https://ws-us.apinator.io', $host);
    }

    public function testCanonicalPathWithoutQueryString(): void
    {
        $method = new \ReflectionMethod($this->client, 'canonicalPath');
        $this->assertSame('/apps/123/events', $method->invoke($this->client, '/apps/123/events'));
    }

    public function testCanonicalPathStripsQueryString(): void
    {
        $method = new \ReflectionMethod($this->client, 'canonicalPath');
        $this->assertSame(
            '/apps/123/channels',
            $method->invoke($this->client, '/apps/123/channels?filter_by_prefix=private-&info=user_count')
        );
    }
}
