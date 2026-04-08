<?php

declare(strict_types=1);

namespace App\Message;

use App\Contract\MessageInterface;

class UserCreatedMessage implements MessageInterface
{
    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly string $occurredAt,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt;
    }

    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'occurredAt' => $this->occurredAt,
        ];
    }
}
