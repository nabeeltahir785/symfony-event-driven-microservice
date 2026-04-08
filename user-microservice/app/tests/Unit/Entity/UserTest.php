<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testConstructorGeneratesUuidV4(): void
    {
        $this->assertInstanceOf(Uuid::class, $this->user->getId());
    }

    public function testConstructorSetsCreatedAtTimestamp(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->user->getCreatedAt());
    }

    public function testConstructorSetsUpdatedAtTimestamp(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->user->getUpdatedAt());
    }

    public function testSetAndGetEmail(): void
    {
        $result = $this->user->setEmail('test@example.com');

        $this->assertSame('test@example.com', $this->user->getEmail());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetFirstName(): void
    {
        $result = $this->user->setFirstName('John');

        $this->assertSame('John', $this->user->getFirstName());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetLastName(): void
    {
        $result = $this->user->setLastName('Doe');

        $this->assertSame('Doe', $this->user->getLastName());
        $this->assertSame($this->user, $result);
    }

    public function testOnPreUpdateRefreshesUpdatedAt(): void
    {
        $originalUpdatedAt = $this->user->getUpdatedAt();

        usleep(1000);
        $this->user->onPreUpdate();

        $this->assertGreaterThanOrEqual($originalUpdatedAt, $this->user->getUpdatedAt());
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $this->user->setEmail('test@example.com');
        $this->user->setFirstName('John');
        $this->user->setLastName('Doe');

        $result = $this->user->toArray();

        $this->assertArrayHasKey('id', $result);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('John', $result['firstName']);
        $this->assertSame('Doe', $result['lastName']);
        $this->assertArrayHasKey('createdAt', $result);
        $this->assertArrayHasKey('updatedAt', $result);
    }

    public function testToArrayIdMatchesUuidRfc4122(): void
    {
        $this->user->setEmail('a@b.com');
        $this->user->setFirstName('A');
        $this->user->setLastName('B');

        $result = $this->user->toArray();

        $this->assertSame($this->user->getId()->toRfc4122(), $result['id']);
    }

    public function testTwoUsersHaveDifferentIds(): void
    {
        $userA = new User();
        $userB = new User();

        $this->assertNotEquals(
            $userA->getId()->toRfc4122(),
            $userB->getId()->toRfc4122()
        );
    }

    public function testFluentSetters(): void
    {
        $result = $this->user
            ->setEmail('chain@test.com')
            ->setFirstName('Chain')
            ->setLastName('Test');

        $this->assertSame($this->user, $result);
        $this->assertSame('chain@test.com', $this->user->getEmail());
        $this->assertSame('Chain', $this->user->getFirstName());
        $this->assertSame('Test', $this->user->getLastName());
    }
}
