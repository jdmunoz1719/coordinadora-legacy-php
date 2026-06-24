<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Tests;

use Coordinadora\Legacy\Models\Incident;
use PHPUnit\Framework\TestCase;

/**
 * Tests para el modelo Incident.
 */
class IncidentTest extends TestCase
{
    /**
     * @test
     */
    public function testIncidentCreationWithValidData(): void
    {
        $data = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'title' => 'Payment service down',
            'description' => 'High latency in payment processing',
            'applicationId' => 'app-123',
            'applicationName' => 'payment-service',
            'severityId' => 5,
            'severityName' => 'CRITICAL',
            'statusName' => 'OPEN',
            'assignedToId' => 'user-456',
            'assignedToName' => 'John Doe',
            'createdById' => 'system-1',
            'createdByName' => 'Monitoring',
            'createdAt' => '2026-06-22T10:30:00Z',
        ];

        $incident = new Incident($data);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $incident->getId());
        $this->assertSame('Payment service down', $incident->getTitle());
        $this->assertSame('payment-service', $incident->getApplicationName());
        $this->assertSame('CRITICAL', $incident->getSeverityName());
        $this->assertSame('OPEN', $incident->getStatusName());
    }

    /**
     * @test
     */
    public function testIncidentMissingIdThrowsException(): void
    {
        $data = [
            'title' => 'Test',
            'description' => 'Test',
            'applicationId' => 'app-1',
            'severityId' => 1,
            'createdById' => 'user-1',
            'createdAt' => '2026-06-22T10:30:00Z',
        ];

        $this->expectException(\InvalidArgumentException::class);
        new Incident($data);
    }

    /**
     * @test
     */
    public function testIncidentToArray(): void
    {
        $data = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'title' => 'Test Incident',
            'description' => 'Description',
            'applicationId' => 'app-1',
            'applicationName' => 'test-app',
            'severityId' => 3,
            'severityName' => 'MEDIUM',
            'statusName' => 'IN_PROGRESS',
            'assignedToId' => 'user-1',
            'assignedToName' => 'John',
            'createdById' => 'user-system',
            'createdByName' => 'System',
            'createdAt' => '2026-06-22T10:30:00Z',
        ];

        $incident = new Incident($data);
        $array = $incident->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('applicationName', $array);
        $this->assertSame('2026-06-22 10:30:00', $array['createdAt']);
    }

    /**
     * @test
     */
    public function testIncidentCreatedAtIsDateTimeImmutable(): void
    {
        $data = [
            'id' => 'test-1',
            'title' => 'Test',
            'description' => 'Test',
            'applicationId' => 'app-1',
            'severityId' => 1,
            'createdById' => 'user-1',
            'createdAt' => '2026-06-22T10:30:00Z',
        ];

        $incident = new Incident($data);
        $createdAt = $incident->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertSame('2026-06-22', $createdAt->format('Y-m-d'));
    }
}
