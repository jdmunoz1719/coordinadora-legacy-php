<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Models;

use DateTimeImmutable;

/**
 * Value Object para Incidente.
 * Datos inmutables obtenidos desde API moderna.
 */
class Incident
{
    private string $id;
    private string $title;
    private string $description;
    private string $applicationId;
    private ?string $applicationName;
    private int $severityId;
    private ?string $severityName;
    private ?string $statusName;
    private ?string $assignedToId;
    private ?string $assignedToName;
    private DateTimeImmutable $createdAt;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? throw new \InvalidArgumentException('Missing "id"');
        $this->title = $data['title'] ?? throw new \InvalidArgumentException('Missing "title"');
        $this->description = $data['description'] ?? '';
        $this->applicationId = $data['applicationId'] ?? throw new \InvalidArgumentException('Missing "applicationId"');
        $this->applicationName = $data['applicationName'] ?? null;
        $this->severityId = (int) ($data['severityId'] ?? throw new \InvalidArgumentException('Missing "severityId"'));
        $this->severityName = $data['severityName'] ?? null;
        $this->statusName = $data['statusName'] ?? null;
        $this->assignedToId = $data['assignedToId'] ?? null;
        $this->assignedToName = $data['assignedToName'] ?? null;

        $createdAtStr = $data['createdAt'] ?? throw new \InvalidArgumentException('Missing "createdAt"');
        $this->createdAt = new DateTimeImmutable($createdAtStr);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function getApplicationName(): ?string
    {
        return $this->applicationName;
    }

    public function getSeverityId(): int
    {
        return $this->severityId;
    }

    public function getSeverityName(): ?string
    {
        return $this->severityName;
    }

    public function getStatusName(): ?string
    {
        return $this->statusName;
    }

    public function getAssignedToId(): ?string
    {
        return $this->assignedToId;
    }

    public function getAssignedToName(): ?string
    {
        return $this->assignedToName;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Convierte a array para serialización (JSON, CSV, etc).
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'applicationId' => $this->applicationId,
            'applicationName' => $this->applicationName,
            'severityId' => $this->severityId,
            'severityName' => $this->severityName,
            'statusName' => $this->statusName,
            'assignedToId' => $this->assignedToId,
            'assignedToName' => $this->assignedToName,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
