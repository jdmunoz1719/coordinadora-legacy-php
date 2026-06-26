<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Tests;

use Coordinadora\Legacy\Models\Incident;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IncidentTest extends TestCase
{
    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'id'              => '550e8400-e29b-41d4-a716-446655440000',
            'title'           => 'Payment service down',
            'description'     => 'High latency in payment processing',
            'applicationId'   => 'app-uuid-123',
            'applicationName' => 'payment-service',
            'severityId'      => 1,
            'severityName'    => 'CRITICAL',
            'severityColor'   => '#ef4444',
            'statusName'      => 'OPEN',
            'assignedToId'    => 'user-456',
            'assignedToName'  => 'John Doe',
            'createdAt'       => '2026-06-22T10:30:00Z',
        ], $overrides);
    }

    // ── Constructor / getters ────────────────────────────────────────────────

    public function testConstructorMapsAllFields(): void
    {
        $incident = new Incident($this->baseData());

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $incident->getId());
        $this->assertSame('Payment service down', $incident->getTitle());
        $this->assertSame('High latency in payment processing', $incident->getDescription());
        $this->assertSame('app-uuid-123', $incident->getApplicationId());
        $this->assertSame('payment-service', $incident->getApplicationName());
        $this->assertSame(1, $incident->getSeverityId());
        $this->assertSame('CRITICAL', $incident->getSeverityName());
        $this->assertSame('#ef4444', $incident->getSeverityColor());
        $this->assertSame('OPEN', $incident->getStatusName());
        $this->assertSame('user-456', $incident->getAssignedToId());
        $this->assertSame('John Doe', $incident->getAssignedToName());
        $this->assertInstanceOf(DateTimeImmutable::class, $incident->getCreatedAt());
    }

    public function testNullableFieldsDefaultToNullWhenAbsent(): void
    {
        $incident = new Incident($this->baseData([
            'applicationName' => null,
            'severityName'    => null,
            'severityColor'   => null,
            'statusName'      => null,
            'assignedToId'    => null,
            'assignedToName'  => null,
        ]));

        $this->assertNull($incident->getApplicationName());
        $this->assertNull($incident->getSeverityName());
        $this->assertNull($incident->getSeverityColor());
        $this->assertNull($incident->getStatusName());
        $this->assertNull($incident->getAssignedToId());
        $this->assertNull($incident->getAssignedToName());
    }

    public function testNullableFieldsMissingKeyDefaultToNull(): void
    {
        $data = [
            'id'            => 'test-id',
            'title'         => 'Test',
            'applicationId' => 'app-1',
            'severityId'    => 2,
            'createdAt'     => '2026-06-01T00:00:00Z',
        ];

        $incident = new Incident($data);

        $this->assertNull($incident->getApplicationName());
        $this->assertNull($incident->getSeverityName());
        $this->assertNull($incident->getSeverityColor());
        $this->assertNull($incident->getStatusName());
        $this->assertNull($incident->getAssignedToId());
        $this->assertNull($incident->getAssignedToName());
        $this->assertSame('', $incident->getDescription());
    }

    public function testSeverityIdIsCastToInt(): void
    {
        $incident = new Incident($this->baseData(['severityId' => '3']));
        $this->assertSame(3, $incident->getSeverityId());
    }

    public function testCreatedAtParsedCorrectly(): void
    {
        $incident = new Incident($this->baseData(['createdAt' => '2026-01-15T08:00:00Z']));

        $this->assertSame('2026-01-15', $incident->getCreatedAt()->format('Y-m-d'));
        $this->assertSame('08:00:00', $incident->getCreatedAt()->format('H:i:s'));
    }

    // ── Constructor validation ───────────────────────────────────────────────

    public function testMissingIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "id"');
        new Incident($this->baseData(['id' => null]));
    }

    public function testMissingTitleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "title"');
        new Incident($this->baseData(['title' => null]));
    }

    public function testMissingApplicationIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "applicationId"');
        new Incident($this->baseData(['applicationId' => null]));
    }

    public function testMissingSeverityIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "severityId"');
        new Incident($this->baseData(['severityId' => null]));
    }

    public function testMissingCreatedAtThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "createdAt"');
        new Incident($this->baseData(['createdAt' => null]));
    }

    // ── toArray ──────────────────────────────────────────────────────────────

    public function testToArrayContainsAllKeys(): void
    {
        $array = (new Incident($this->baseData()))->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('applicationId', $array);
        $this->assertArrayHasKey('applicationName', $array);
        $this->assertArrayHasKey('severityId', $array);
        $this->assertArrayHasKey('severityName', $array);
        $this->assertArrayHasKey('severityColor', $array);
        $this->assertArrayHasKey('statusName', $array);
        $this->assertArrayHasKey('assignedToId', $array);
        $this->assertArrayHasKey('assignedToName', $array);
        $this->assertArrayHasKey('createdAt', $array);
    }

    public function testToArrayFormatsCreatedAt(): void
    {
        $array = (new Incident($this->baseData(['createdAt' => '2026-06-22T10:30:00Z'])))->toArray();
        $this->assertSame('2026-06-22 10:30:00', $array['createdAt']);
    }

    public function testToArraySeverityColorWhenNull(): void
    {
        $array = (new Incident($this->baseData(['severityColor' => null])))->toArray();
        $this->assertNull($array['severityColor']);
    }

    public function testToArraySeverityColorWhenSet(): void
    {
        $array = (new Incident($this->baseData(['severityColor' => '#3b82f6'])))->toArray();
        $this->assertSame('#3b82f6', $array['severityColor']);
    }
}
