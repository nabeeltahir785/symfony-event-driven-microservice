<?php

declare(strict_types=1);

namespace App\Message;

class UserEventMessage
{
    public const TYPE_CREATED = 'user.created';
    public const TYPE_UPDATED = 'user.updated';
    public const TYPE_DELETED = 'user.deleted';

    public function __construct(
        private readonly string $eventType,
        private readonly string $userId,
        private readonly array $payload,
        private readonly string $occurredAt,
    ) {
    }

    public function getEventType(): string
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
            'eventType' => $this->eventType,
            'userId' => $this->userId,
            'payload' => $this->payload,
            'occurredAt' => $this->occurredAt,
        ];
    }
}
