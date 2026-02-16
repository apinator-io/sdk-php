<?php

namespace Apinator\Tests;

use PHPUnit\Framework\TestCase;
use Apinator\Auth;
use Apinator\Errors\ApiException;
use Apinator\Errors\AuthenticationException;
use Apinator\Errors\ValidationException;
use Apinator\Apinator;
use Apinator\Webhook;

class ConformanceTest extends TestCase
{
    private function loadFixture(string $name): array
    {
        $path = dirname(__DIR__, 3) . "/backend/specs/conformance/{$name}";

        if (!file_exists($path)) {
            $this->markTestSkipped("Conformance fixture not available: {$name} (only runs inside monorepo)");
        }

        $raw = file_get_contents($path);
        $this->assertNotFalse($raw, "failed to read fixture: {$path}");

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded, "failed to parse fixture: {$path}");
        return $decoded;
    }

    public function testHMACFixtureVectors(): void
    {
        $fixture = $this->loadFixture('hmac-request.v1.json');
        foreach ($fixture['cases'] as $case) {
            $signature = Auth::signRequest(
                $case['secret'],
                $case['method'],
                $case['path'],
                $case['body'],
                $case['timestamp'],
            );
            $this->assertSame($case['expected_signature'], $signature, "failed HMAC case {$case['name']}");
        }
    }

    public function testQueryNotSignedCaseUsesCanonicalPath(): void
    {
        $fixture = $this->loadFixture('hmac-request.v1.json');
        $queryCase = null;
        foreach ($fixture['cases'] as $case) {
            if (($case['name'] ?? '') === 'query-not-signed') {
                $queryCase = $case;
                break;
            }
        }
        $this->assertIsArray($queryCase, 'missing query-not-signed fixture case');

        $canonical = Auth::signRequest(
            $queryCase['secret'],
            $queryCase['method'],
            $queryCase['path'],
            $queryCase['body'],
            $queryCase['timestamp'],
        );
        $this->assertSame($queryCase['expected_signature'], $canonical);

        $legacy = Auth::signRequest(
            $queryCase['secret'],
            $queryCase['method'],
            $queryCase['raw_path'],
            $queryCase['body'],
            $queryCase['timestamp'],
        );
        $this->assertNotSame($queryCase['expected_signature'], $legacy);
    }

    public function testChannelFixtureVectors(): void
    {
        $fixture = $this->loadFixture('channel-auth.v1.json');
        foreach ($fixture['cases'] as $case) {
            $channelData = $case['channel_data'] ?? null;
            $signature = Auth::signChannel(
                $case['secret'],
                $case['socket_id'],
                $case['channel_name'],
                $channelData,
            );
            $this->assertSame($case['expected_signature'], $signature, "failed channel signature case {$case['name']}");

            $auth = Auth::authenticateChannel(
                $case['secret'],
                $case['key'],
                $case['socket_id'],
                $case['channel_name'],
                $channelData,
            );
            $this->assertSame($case['expected_auth'], $auth['auth'], "failed channel auth case {$case['name']}");
        }
    }

    public function testWebhookFixtureVectors(): void
    {
        $fixture = $this->loadFixture('webhook-signature.v1.json');
        foreach ($fixture['cases'] as $case) {
            $signature = Webhook::sign($case['secret'], $case['timestamp'], $case['body']);
            $this->assertSame($case['expected_signature'], $signature, "failed webhook case {$case['name']}");
        }
    }

    public function testClientUsesCanonicalPathRule(): void
    {
        $client = new Apinator(
            appId: 'test-app-id',
            key: 'test-key',
            secret: 'test-secret',
            cluster: 'eu',
        );

        $method = new \ReflectionMethod($client, 'canonicalPath');
        $canonical = $method->invoke($client, '/apps/123/channels?filter_by_prefix=private-');

        $this->assertSame('/apps/123/channels', $canonical);
    }

    public function testClientMapsRFC7807UnauthorizedToAuthenticationException(): void
    {
        $client = new Apinator(
            appId: 'test-app-id',
            key: 'test-key',
            secret: 'test-secret',
            cluster: 'eu',
        );

        $method = new \ReflectionMethod($client, 'throwForStatus');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('signature mismatch');

        $method->invoke(
            $client,
            401,
            '{"type":"https://docs.apinator.io/problems/unauthorized","title":"Unauthorized","status":401,"detail":"signature mismatch","code":"unauthorized"}'
        );
    }

    public function testClientMapsRFC7807ValidationToValidationException(): void
    {
        $client = new Apinator(
            appId: 'test-app-id',
            key: 'test-key',
            secret: 'test-secret',
            cluster: 'eu',
        );

        $method = new \ReflectionMethod($client, 'throwForStatus');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('invalid request');

        $method->invoke(
            $client,
            422,
            '{"type":"https://docs.apinator.io/problems/unprocessable_entity","title":"Unprocessable Entity","status":422,"detail":"invalid request","code":"unprocessable_entity"}'
        );
    }

    public function testClientMapsRFC7807ServerErrorsToApiException(): void
    {
        $client = new Apinator(
            appId: 'test-app-id',
            key: 'test-key',
            secret: 'test-secret',
            cluster: 'eu',
        );

        $method = new \ReflectionMethod($client, 'throwForStatus');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Internal Server Error');

        $method->invoke(
            $client,
            500,
            '{"type":"https://docs.apinator.io/problems/internal_error","title":"Internal Server Error","status":500,"detail":"","code":"internal_error"}'
        );
    }

    public function testClientMaps403ToAuthenticationException(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('forbidden');

        $method->invoke($client, 403, '{"detail":"forbidden"}');
    }

    public function testClientMaps400ToValidationException(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('bad request');

        $method->invoke($client, 400, '{"detail":"bad request"}');
    }

    public function testClientMaps404ToApiException(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        try {
            $method->invoke($client, 404, '{"detail":"not found"}');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('not found', $e->getMessage());
            $this->assertSame(404, $e->statusCode);
        }
    }

    public function testClientMaps429ToApiException(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        try {
            $method->invoke($client, 429, '{"detail":"rate limited"}');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('rate limited', $e->getMessage());
            $this->assertSame(429, $e->statusCode);
        }
    }

    public function testClientMaps502ToApiException(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        try {
            $method->invoke($client, 502, '{"title":"Bad Gateway"}');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Bad Gateway', $e->getMessage());
            $this->assertSame(502, $e->statusCode);
        }
    }

    public function testThrowForStatusFallsBackToTitleWhenDetailEmpty(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        try {
            $method->invoke($client, 500, '{"title":"Internal Server Error","detail":""}');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Internal Server Error', $e->getMessage());
        }
    }

    public function testThrowForStatusFallsBackToTitleWhenDetailMissing(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        try {
            $method->invoke($client, 500, '{"title":"Service Unavailable"}');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Service Unavailable', $e->getMessage());
        }
    }

    public function testThrowForStatusHandlesNonJsonResponse(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        try {
            $method->invoke($client, 500, '<html>Server Error</html>');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('API request failed with status 500', $e->getMessage());
            $this->assertSame(500, $e->statusCode);
            $this->assertSame('<html>Server Error</html>', $e->responseBody);
        }
    }

    public function testThrowForStatusHandlesEmptyResponse(): void
    {
        $client = $this->makeClient();
        $method = new \ReflectionMethod($client, 'throwForStatus');

        try {
            $method->invoke($client, 500, '');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('API request failed with status 500', $e->getMessage());
        }
    }

    public function testApiExceptionCarriesStatusCodeAndBody(): void
    {
        $e = new ApiException('test error', 503, '{"error":"unavailable"}');

        $this->assertSame('test error', $e->getMessage());
        $this->assertSame(503, $e->statusCode);
        $this->assertSame('{"error":"unavailable"}', $e->responseBody);
        $this->assertSame(503, $e->getCode());
    }

    public function testApiExceptionWithNullBody(): void
    {
        $e = new ApiException('connection failed', 0);

        $this->assertSame(0, $e->statusCode);
        $this->assertNull($e->responseBody);
    }

    public function testExceptionInheritanceChain(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new ApiException('test', 500));
        $this->assertInstanceOf(\Apinator\Errors\RealtimeException::class, new ApiException('test', 500));
        $this->assertInstanceOf(\Apinator\Errors\RealtimeException::class, new AuthenticationException('test'));
        $this->assertInstanceOf(\Apinator\Errors\RealtimeException::class, new ValidationException('test'));
        $this->assertInstanceOf(\RuntimeException::class, new AuthenticationException('test'));
        $this->assertInstanceOf(\RuntimeException::class, new ValidationException('test'));
    }

    private function makeClient(): Apinator
    {
        return new Apinator(
            appId: 'test-app-id',
            key: 'test-key',
            secret: 'test-secret',
            cluster: 'eu',
        );
    }
}
