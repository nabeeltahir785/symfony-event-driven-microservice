<?php

declare(strict_types=1);

namespace App\Message;

use App\Contract\MessageInterface;
use App\Enum\UserEventType;

class UserEventMessage implements MessageInterface
{
    public function __construct(
        private readonly UserEventType $eventType,
        private readonly string $userId,
        private readonly array $payload,
        private readonly string $occurredAt,
    ) {
    }

    public function getEventType(): UserEventType
    {
        return $this->eventType;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt;
    }

    public function toArray(): array
    {
        return [
            'eventType' => $this->eventType->value,
            'userId' => $this->userId,
            'payload' => $this->payload,
            'occurredAt' => $this->occurredAt,
        ];
    }
}
