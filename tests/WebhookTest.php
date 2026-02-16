<?php

namespace Apinator\Tests;

use PHPUnit\Framework\TestCase;
use Apinator\Errors\ValidationException;
use Apinator\Webhook;

class WebhookTest extends TestCase
{
    private const SECRET = 'my-secret-key';

    public function testVerifyValidSignature(): void
    {
        $timestamp = '1700000000';
        $body = '{"event":"test"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body);

        $this->assertTrue($result);
    }

    public function testVerifyValidSignatureWithoutPrefix(): void
    {
        $timestamp = '1700000000';
        $body = '{"event":"test"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body);

        $this->assertTrue($result);
    }

    public function testVerifyTamperedBody(): void
    {
        $timestamp = '1700000000';
        $body = '{"event":"test"}';
        $tamperedBody = '{"event":"modified"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $tamperedBody);

        $this->assertFalse($result);
    }

    public function testVerifyWrongSecret(): void
    {
        $timestamp = '1700000000';
        $body = '{"event":"test"}';

        $signature = Webhook::sign('wrong-secret', $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body);

        $this->assertFalse($result);
    }

    public function testVerifyExpiredTimestamp(): void
    {
        // Timestamp 1 hour ago
        $timestamp = (string)(time() - 3600);
        $body = '{"event":"test"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Webhook timestamp too old');

        Webhook::verify(self::SECRET, $headers, $body, maxAge: 300);
    }

    public function testVerifyFreshTimestamp(): void
    {
        // Current timestamp
        $timestamp = (string)time();
        $body = '{"event":"test"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body, maxAge: 300);

        $this->assertTrue($result);
    }

    public function testVerifyMissingSignatureHeader(): void
    {
        $headers = [
            'X-Realtime-Timestamp' => '1700000000',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing x-realtime-signature header');

        Webhook::verify(self::SECRET, $headers, '{"event":"test"}');
    }

    public function testVerifyMissingTimestampHeader(): void
    {
        $headers = [
            'X-Realtime-Signature' => 'sha256=abc123',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing x-realtime-timestamp header');

        Webhook::verify(self::SECRET, $headers, '{"event":"test"}');
    }

    public function testVerifyCaseInsensitiveHeaders(): void
    {
        $timestamp = '1700000000';
        $body = '{"event":"test"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        // Headers with mixed case
        $headers = [
            'x-REALTIME-signature' => 'sha256=' . $signature,
            'X-realtime-TIMESTAMP' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body);

        $this->assertTrue($result);
    }

    public function testSign(): void
    {
        $timestamp = '1700000000';
        $payload = '{"event":"test","data":"value"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $payload);

        $this->assertNotEmpty($signature);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);

        // Verify the signature is correct
        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET);
        $this->assertSame($expected, $signature);
    }

    public function testSignConsistency(): void
    {
        $timestamp = '1700000000';
        $payload = '{"event":"test"}';

        $sig1 = Webhook::sign(self::SECRET, $timestamp, $payload);
        $sig2 = Webhook::sign(self::SECRET, $timestamp, $payload);

        $this->assertSame($sig1, $sig2);
    }

    public function testVerifyWithoutMaxAge(): void
    {
        // Very old timestamp should pass if maxAge is not specified
        $timestamp = '1000000000'; // Year 2001
        $body = '{"event":"test"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body);

        $this->assertTrue($result);
    }

    public function testVerifyEmptyBody(): void
    {
        $timestamp = '1700000000';
        $body = '';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body);

        $this->assertTrue($result);
    }

    public function testVerifyFutureTimestampPassesWithMaxAge(): void
    {
        // Future timestamp produces negative age, which is always < maxAge
        $timestamp = (string)(time() + 60);
        $body = '{"event":"test"}';

        $signature = Webhook::sign(self::SECRET, $timestamp, $body);

        $headers = [
            'X-Realtime-Signature' => 'sha256=' . $signature,
            'X-Realtime-Timestamp' => $timestamp,
        ];

        $result = Webhook::verify(self::SECRET, $headers, $body, maxAge: 300);

        $this->assertTrue($result);
    }

    public function testSignEmptyPayload(): void
    {
        $signature = Webhook::sign(self::SECRET, '1700000000', '');

        $expected = hash_hmac('sha256', '1700000000.', self::SECRET);
        $this->assertSame($expected, $signature);
    }
}
