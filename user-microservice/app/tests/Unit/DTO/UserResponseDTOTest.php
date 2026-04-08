<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\UserResponseDTO;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserResponseDTOTest extends TestCase
{
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');

        return $user;
    }

    public function testFromEntityMapsAllFields(): void
    {
        $user = $this->createUser();

        $dto = UserResponseDTO::fromEntity($user);

        $this->assertSame($user->getId()->toRfc4122(), $dto->id);
        $this->assertSame('test@example.com', $dto->email);
        $this->assertSame('John', $dto->firstName);
        $this->assertSame('Doe', $dto->lastName);
        $this->assertSame(
            $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $dto->createdAt
        );
        $this->assertSame(
            $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            $dto->updatedAt
        );
    }

    public function testToArrayReturnsExpectedKeys(): void
    {
        $user = $this->createUser();
        $dto = UserResponseDTO::fromEntity($user);

        $result = $dto->toArray();

        $expectedKeys = ['id', 'email', 'firstName', 'lastName', 'createdAt', 'updatedAt'];
        $this->assertSame($expectedKeys, array_keys($result));
    }

    public function testToArrayValuesMatchProperties(): void
    {
        $user = $this->createUser();
        $dto = UserResponseDTO::fromEntity($user);

        $result = $dto->toArray();

        $this->assertSame($dto->id, $result['id']);
        $this->assertSame($dto->email, $result['email']);
        $this->assertSame($dto->firstName, $result['firstName']);
        $this->assertSame($dto->lastName, $result['lastName']);
        $this->assertSame($dto->createdAt, $result['createdAt']);
        $this->assertSame($dto->updatedAt, $result['updatedAt']);
    }

    public function testCollectionToArrayMapsMultipleUsers(): void
    {
        $userA = $this->createUser();
        $userB = new User();
        $userB->setEmail('jane@example.com');
        $userB->setFirstName('Jane');
        $userB->setLastName('Smith');

        $result = UserResponseDTO::collectionToArray([$userA, $userB]);

        $this->assertCount(2, $result);
        $this->assertSame('test@example.com', $result[0]['email']);
        $this->assertSame('jane@example.com', $result[1]['email']);
    }

    public function testCollectionToArrayReturnsEmptyForEmptyInput(): void
    {
        $result = UserResponseDTO::collectionToArray([]);

        $this->assertSame([], $result);
    }
}
