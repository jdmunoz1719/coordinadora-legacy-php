<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Tests;

use Coordinadora\Legacy\Exceptions\IncidentClientException;
use PHPUnit\Framework\TestCase;

class IncidentClientExceptionTest extends TestCase
{
    // ── Constructor ──────────────────────────────────────────────────────────

    public function testDefaultHttpCodeIsNull(): void
    {
        $e = new IncidentClientException('Something went wrong');
        $this->assertNull($e->getHttpCode());
    }

    public function testDefaultResponseBodyIsNull(): void
    {
        $e = new IncidentClientException('Something went wrong');
        $this->assertNull($e->getResponseBody());
    }

    public function testConstructorSetsHttpCode(): void
    {
        $e = new IncidentClientException('Error', 0, 503);
        $this->assertSame(503, $e->getHttpCode());
    }

    public function testConstructorSetsResponseBody(): void
    {
        $e = new IncidentClientException('Error', 0, 500, '{"error":"internal"}');
        $this->assertSame('{"error":"internal"}', $e->getResponseBody());
    }

    public function testConstructorSetsMessage(): void
    {
        $e = new IncidentClientException('Custom message');
        $this->assertSame('Custom message', $e->getMessage());
    }

    // ── fromHttpError ────────────────────────────────────────────────────────

    public function testFromHttpError400(): void
    {
        $e = IncidentClientException::fromHttpError(400, 'body');
        $this->assertSame(400, $e->getHttpCode());
        $this->assertStringContainsString('Bad Request', $e->getMessage());
    }

    public function testFromHttpError401(): void
    {
        $e = IncidentClientException::fromHttpError(401, '');
        $this->assertSame(401, $e->getHttpCode());
        $this->assertStringContainsString('Unauthorized', $e->getMessage());
    }

    public function testFromHttpError403(): void
    {
        $e = IncidentClientException::fromHttpError(403, '');
        $this->assertSame(403, $e->getHttpCode());
        $this->assertStringContainsString('Forbidden', $e->getMessage());
    }

    public function testFromHttpError404(): void
    {
        $e = IncidentClientException::fromHttpError(404, '');
        $this->assertSame(404, $e->getHttpCode());
        $this->assertStringContainsString('Not Found', $e->getMessage());
    }

    public function testFromHttpError408(): void
    {
        $e = IncidentClientException::fromHttpError(408, '');
        $this->assertSame(408, $e->getHttpCode());
        $this->assertStringContainsString('Request Timeout', $e->getMessage());
    }

    public function testFromHttpError429(): void
    {
        $e = IncidentClientException::fromHttpError(429, '');
        $this->assertSame(429, $e->getHttpCode());
        $this->assertStringContainsString('Too Many Requests', $e->getMessage());
    }

    public function testFromHttpError500(): void
    {
        $e = IncidentClientException::fromHttpError(500, 'error body');
        $this->assertSame(500, $e->getHttpCode());
        $this->assertSame('error body', $e->getResponseBody());
        $this->assertStringContainsString('Internal Server Error', $e->getMessage());
    }

    public function testFromHttpError502(): void
    {
        $e = IncidentClientException::fromHttpError(502, '');
        $this->assertStringContainsString('Bad Gateway', $e->getMessage());
    }

    public function testFromHttpError503(): void
    {
        $e = IncidentClientException::fromHttpError(503, '');
        $this->assertStringContainsString('Service Unavailable', $e->getMessage());
    }

    public function testFromHttpErrorUnknownCode(): void
    {
        $e = IncidentClientException::fromHttpError(418, '');
        $this->assertSame(418, $e->getHttpCode());
        $this->assertStringContainsString('418', $e->getMessage());
    }

    // ── timeout ──────────────────────────────────────────────────────────────

    public function testTimeoutContainsUrlAndSeconds(): void
    {
        $e = IncidentClientException::timeout('http://localhost:3001/api/incidents/open', 10);
        $this->assertStringContainsString('localhost:3001', $e->getMessage());
        $this->assertStringContainsString('10s', $e->getMessage());
    }

    // ── connectionError ──────────────────────────────────────────────────────

    public function testConnectionErrorContainsUrlAndReason(): void
    {
        $e = IncidentClientException::connectionError('http://localhost:3001', 'Connection refused');
        $this->assertStringContainsString('localhost:3001', $e->getMessage());
        $this->assertStringContainsString('Connection refused', $e->getMessage());
    }
}
