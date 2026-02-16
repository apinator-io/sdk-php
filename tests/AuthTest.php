<?php

namespace Apinator\Tests;

use PHPUnit\Framework\TestCase;
use Apinator\Auth;

class AuthTest extends TestCase
{
    private const SECRET = 'my-secret-key';

    public function testSignRequestEmptyBody(): void
    {
        $signature = Auth::signRequest(
            secret: self::SECRET,
            method: 'GET',
            path: '/apps/123/channels',
            body: '',
            timestamp: 1700000000
        );

        // With empty body, bodyMD5 should be empty string
        // Expected signature string: "1700000000\nGET\n/apps/123/channels\n"
        $this->assertNotEmpty($signature);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);

        // Verify with known expected value
        $expected = hash_hmac('sha256', "1700000000\nGET\n/apps/123/channels\n", self::SECRET);
        $this->assertSame($expected, $signature);
    }

    public function testSignRequestWithBody(): void
    {
        $body = '{"name":"test"}';
        $signature = Auth::signRequest(
            secret: self::SECRET,
            method: 'POST',
            path: '/apps/123/events',
            body: $body,
            timestamp: 1700000000
        );

        $this->assertNotEmpty($signature);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);

        // Verify with known expected value
        $bodyMD5 = md5($body);
        $expected = hash_hmac('sha256', "1700000000\nPOST\n/apps/123/events\n{$bodyMD5}", self::SECRET);
        $this->assertSame($expected, $signature);
    }

    public function testSignChannelPrivate(): void
    {
        $signature = Auth::signChannel(
            secret: self::SECRET,
            socketId: '12345.67890',
            channelName: 'private-chat'
        );

        $this->assertNotEmpty($signature);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);

        // Verify with known expected value
        $expected = hash_hmac('sha256', '12345.67890:private-chat', self::SECRET);
        $this->assertSame($expected, $signature);
    }

    public function testSignChannelPresence(): void
    {
        $channelData = '{"user_id":"user1"}';
        $signature = Auth::signChannel(
            secret: self::SECRET,
            socketId: '12345.67890',
            channelName: 'presence-chat',
            channelData: $channelData
        );

        $this->assertNotEmpty($signature);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);

        // Verify with known expected value
        $expected = hash_hmac('sha256', '12345.67890:presence-chat:{"user_id":"user1"}', self::SECRET);
        $this->assertSame($expected, $signature);
    }

    public function testAuthenticateChannel(): void
    {
        $key = 'test-key';
        $result = Auth::authenticateChannel(
            secret: self::SECRET,
            key: $key,
            socketId: '12345.67890',
            channelName: 'private-chat'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayNotHasKey('channel_data', $result);

        // Verify auth format: "key:signature"
        $this->assertStringStartsWith($key . ':', $result['auth']);

        // Extract and verify signature
        $parts = explode(':', $result['auth'], 2);
        $this->assertCount(2, $parts);
        $this->assertSame($key, $parts[0]);

        $expectedSig = hash_hmac('sha256', '12345.67890:private-chat', self::SECRET);
        $this->assertSame($expectedSig, $parts[1]);
    }

    public function testAuthenticateChannelWithChannelData(): void
    {
        $key = 'test-key';
        $channelData = '{"user_id":"user1"}';

        $result = Auth::authenticateChannel(
            secret: self::SECRET,
            key: $key,
            socketId: '12345.67890',
            channelName: 'presence-chat',
            channelData: $channelData
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayHasKey('channel_data', $result);
        $this->assertSame($channelData, $result['channel_data']);

        // Extract and verify signature
        $parts = explode(':', $result['auth'], 2);
        $this->assertCount(2, $parts);

        $expectedSig = hash_hmac('sha256', '12345.67890:presence-chat:{"user_id":"user1"}', self::SECRET);
        $this->assertSame($expectedSig, $parts[1]);
    }

    public function testSignRequestConsistency(): void
    {
        // Test that signing the same request multiple times produces the same result
        $body = '{"test":"data"}';
        $timestamp = 1700000000;

        $sig1 = Auth::signRequest(self::SECRET, 'POST', '/test', $body, $timestamp);
        $sig2 = Auth::signRequest(self::SECRET, 'POST', '/test', $body, $timestamp);

        $this->assertSame($sig1, $sig2);
    }

    public function testSignChannelConsistency(): void
    {
        // Test that signing the same channel multiple times produces the same result
        $sig1 = Auth::signChannel(self::SECRET, 'socket1', 'channel1');
        $sig2 = Auth::signChannel(self::SECRET, 'socket1', 'channel1');

        $this->assertSame($sig1, $sig2);
    }
}
