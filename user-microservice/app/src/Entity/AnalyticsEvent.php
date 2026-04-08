<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AnalyticsEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AnalyticsEventRepository::class)]
#[ORM\Table(name: 'analytics_events')]
#[ORM\Index(columns: ['event_type'], name: 'idx_event_type')]
#[ORM\Index(columns: ['aggregate_id'], name: 'idx_aggregate_id')]
#[ORM\Index(columns: ['processed_at'], name: 'idx_processed_at')]
class AnalyticsEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 50)]
    private string $eventType;

    #[ORM\Column(length: 36)]
    private string $aggregateId;

    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $processedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->processedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function setAggregateId(string $aggregateId): self
    {
        $this->aggregateId = $aggregateId;
        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;
        return $this;
    }

    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(\DateTimeImmutable $processedAt): self
    {
        $this->processedAt = $processedAt;
        return $this;
    }
}
