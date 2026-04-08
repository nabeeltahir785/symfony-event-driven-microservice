<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;

class UserResponseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId()->toRfc4122(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            createdAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public static function collectionToArray(array $users): array
    {
        return array_map(
            fn(User $user) => self::fromEntity($user)->toArray(),
            $users
        );
    }
}
