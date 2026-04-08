<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AnalyticsEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class AnalyticsEventTest extends TestCase
{
    private AnalyticsEvent $event;

    protected function setUp(): void
    {
        $this->event = new AnalyticsEvent();
    }

    public function testConstructorGeneratesUuidV4(): void
    {
        $this->assertInstanceOf(Uuid::class, $this->event->getId());
    }

    public function testConstructorSetsProcessedAt(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->event->getProcessedAt());
    }

    public function testSetAndGetEventType(): void
    {
        $result = $this->event->setEventType('user.created');

        $this->assertSame('user.created', $this->event->getEventType());
        $this->assertSame($this->event, $result);
    }

    public function testSetAndGetAggregateId(): void
    {
        $result = $this->event->setAggregateId('abc-123');

        $this->assertSame('abc-123', $this->event->getAggregateId());
        $this->assertSame($this->event, $result);
    }

    public function testSetAndGetPayload(): void
    {
        $payload = ['key' => 'value', 'nested' => ['a' => 1]];
        $result = $this->event->setPayload($payload);

        $this->assertSame($payload, $this->event->getPayload());
        $this->assertSame($this->event, $result);
    }

    public function testSetAndGetOccurredAt(): void
    {
        $timestamp = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $result = $this->event->setOccurredAt($timestamp);

        $this->assertSame($timestamp, $this->event->getOccurredAt());
        $this->assertSame($this->event, $result);
    }

    public function testSetAndGetProcessedAt(): void
    {
        $timestamp = new \DateTimeImmutable('2026-06-15T12:00:00+00:00');
        $result = $this->event->setProcessedAt($timestamp);

        $this->assertSame($timestamp, $this->event->getProcessedAt());
        $this->assertSame($this->event, $result);
    }

    public function testFluentSetters(): void
    {
        $occurredAt = new \DateTimeImmutable();

        $result = $this->event
            ->setEventType('user.deleted')
            ->setAggregateId('xyz-789')
            ->setPayload(['data' => true])
            ->setOccurredAt($occurredAt);

        $this->assertSame($this->event, $result);
    }
}
