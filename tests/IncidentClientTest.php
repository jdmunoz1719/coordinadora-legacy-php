<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Tests;

use Coordinadora\Legacy\Exceptions\IncidentClientException;
use Coordinadora\Legacy\IncidentClient;
use Coordinadora\Legacy\Models\Incident;
use PHPUnit\Framework\TestCase;

/**
 * Subclase de prueba que intercepta cURL para tests unitarios rápidos.
 */
class StubIncidentClient extends IncidentClient
{
    /** @var array<array{body: string, httpCode: int}> */
    private array $queue = [];
    private int $callCount = 0;
    public string $lastUrl = '';

    public function queueResponse(string $body, int $httpCode = 200): void
    {
        $this->queue[] = ['body' => $body, 'httpCode' => $httpCode];
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    protected function executeCurl(string $method, string $url): string
    {
        $this->lastUrl = $url;
        $response = $this->queue[$this->callCount] ?? ['body' => '', 'httpCode' => 500];
        $this->callCount++;

        if ($response['httpCode'] >= 400) {
            throw IncidentClientException::fromHttpError($response['httpCode'], $response['body']);
        }

        return $response['body'];
    }
}

class IncidentClientTest extends TestCase
{
    private function makeClient(): StubIncidentClient
    {
        $client = new StubIncidentClient([
            'base_url'  => 'http://localhost:3001',
            'log_path'  => sys_get_temp_dir() . '/test-client-' . uniqid() . '.log',
            'log_level' => 'error',
        ]);
        $client->setRetryDelayMs(0);
        return $client;
    }

    private function incidentJson(array $overrides = []): array
    {
        return array_merge([
            'id'              => 'inc-uuid-001',
            'title'           => 'Test incident',
            'description'     => 'Something broke',
            'applicationId'   => 'app-uuid-001',
            'applicationName' => 'MyApp',
            'severityId'      => 2,
            'severityName'    => 'HIGH',
            'severityColor'   => '#f97316',
            'statusName'      => 'OPEN',
            'assignedToId'    => null,
            'assignedToName'  => null,
            'createdAt'       => '2026-06-22T10:00:00Z',
        ], $overrides);
    }

    private function paginatedResponse(array $incidents = [], int $total = 1, int $page = 1, int $limit = 20): string
    {
        return json_encode([
            'data'            => $incidents,
            'total'           => $total,
            'page'            => $page,
            'limit'           => $limit,
            'totalPages'      => (int) ceil($total / $limit),
            'hasNextPage'     => $page < (int) ceil($total / $limit),
            'hasPreviousPage' => $page > 1,
        ]);
    }

    // ── Success path ──────────────────────────────────────────────────────────

    public function testGetOpenIncidentsReturnsIncidentObjects(): void
    {
        $client = $this->makeClient();
        $client->queueResponse($this->paginatedResponse([$this->incidentJson()]));

        $result = $client->getOpenIncidents();

        $this->assertCount(1, $result['incidents']);
        $this->assertInstanceOf(Incident::class, $result['incidents'][0]);
    }

    public function testGetOpenIncidentsReturnsPaginationMeta(): void
    {
        $client = $this->makeClient();
        $client->queueResponse($this->paginatedResponse([$this->incidentJson()], 42, 2, 10));

        $result = $client->getOpenIncidents(['page' => 2, 'limit' => 10]);

        $this->assertSame(42, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(10, $result['limit']);
        $this->assertSame(5, $result['totalPages']);
    }

    public function testGetOpenIncidentsMapsAllModelFields(): void
    {
        $client = $this->makeClient();
        $item   = $this->incidentJson(['severityColor' => '#ef4444', 'assignedToName' => 'Jane']);
        $client->queueResponse($this->paginatedResponse([$item]));

        $incident = $client->getOpenIncidents()['incidents'][0];

        $this->assertSame('inc-uuid-001', $incident->getId());
        $this->assertSame('Test incident', $incident->getTitle());
        $this->assertSame('#ef4444', $incident->getSeverityColor());
        $this->assertSame('Jane', $incident->getAssignedToName());
        $this->assertSame('HIGH', $incident->getSeverityName());
    }

    public function testGetOpenIncidentsReturnsEmptyArrayWhenNoData(): void
    {
        $client = $this->makeClient();
        $client->queueResponse($this->paginatedResponse([], 0));

        $result = $client->getOpenIncidents();

        $this->assertSame([], $result['incidents']);
        $this->assertSame(0, $result['total']);
    }

    // ── Query params ──────────────────────────────────────────────────────────

    public function testGetOpenIncidentsBuildsUrlWithFilters(): void
    {
        $client = $this->makeClient();
        $client->queueResponse($this->paginatedResponse());

        $client->getOpenIncidents([
            'applicationId' => 'app-abc',
            'severityId'    => 2,
            'page'          => 3,
            'limit'         => 50,
        ]);

        $this->assertStringContainsString('applicationId=app-abc', $client->lastUrl);
        $this->assertStringContainsString('severityId=2', $client->lastUrl);
        $this->assertStringContainsString('page=3', $client->lastUrl);
        $this->assertStringContainsString('limit=50', $client->lastUrl);
    }

    public function testGetOpenIncidentsOmitsEmptyFilters(): void
    {
        $client = $this->makeClient();
        $client->queueResponse($this->paginatedResponse());

        $client->getOpenIncidents(['applicationId' => '', 'severityId' => 0]);

        $this->assertStringNotContainsString('applicationId', $client->lastUrl);
        $this->assertStringNotContainsString('severityId', $client->lastUrl);
    }

    public function testLimitCappedAt100(): void
    {
        $client = $this->makeClient();
        $client->queueResponse($this->paginatedResponse());

        $client->getOpenIncidents(['limit' => 999]);

        $this->assertStringContainsString('limit=100', $client->lastUrl);
    }

    // ── Error / retry behavior ────────────────────────────────────────────────

    public function testHttp4xxThrowsWithoutRetry(): void
    {
        $client = $this->makeClient();
        $client->queueResponse('Unauthorized', 401);

        $this->expectException(IncidentClientException::class);
        $client->getOpenIncidents();

        $this->assertSame(1, $client->getCallCount());
    }

    public function testHttp404ThrowsWithoutRetry(): void
    {
        $client = $this->makeClient();
        $client->queueResponse('Not found', 404);

        $this->expectException(IncidentClientException::class);
        $client->getOpenIncidents();
    }

    public function testHttp5xxRetriesAndThrowsAfterMaxRetries(): void
    {
        $client = $this->makeClient();
        $client->setMaxRetries(2);

        // 3 responses queued: initial + 2 retries = all 500
        $client->queueResponse('error', 500);
        $client->queueResponse('error', 500);
        $client->queueResponse('error', 500);

        $this->expectException(IncidentClientException::class);

        try {
            $client->getOpenIncidents();
        } finally {
            $this->assertSame(3, $client->getCallCount());
        }
    }

    public function testSucceedsOnSecondAttemptAfter5xx(): void
    {
        $client = $this->makeClient();
        $client->setMaxRetries(2);

        $client->queueResponse('error', 503);
        $client->queueResponse($this->paginatedResponse([$this->incidentJson()]));

        $result = $client->getOpenIncidents();

        $this->assertCount(1, $result['incidents']);
        $this->assertSame(2, $client->getCallCount());
    }

    // ── Invalid response ──────────────────────────────────────────────────────

    public function testInvalidJsonResponseThrows(): void
    {
        $client = $this->makeClient();
        $client->setMaxRetries(0);
        $client->queueResponse('not json at all', 200);

        $this->expectException(IncidentClientException::class);
        $client->getOpenIncidents();
    }

    public function testMissingDataKeyInResponseThrows(): void
    {
        $client = $this->makeClient();
        $client->setMaxRetries(0);
        $client->queueResponse(json_encode(['items' => [], 'total' => 0]));

        $this->expectException(IncidentClientException::class);
        $client->getOpenIncidents();
    }
}
