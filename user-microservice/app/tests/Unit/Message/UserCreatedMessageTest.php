<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Contract\MessageInterface;
use App\Message\UserCreatedMessage;
use PHPUnit\Framework\TestCase;

class UserCreatedMessageTest extends TestCase
{
    private UserCreatedMessage $message;

    protected function setUp(): void
    {
        $this->message = new UserCreatedMessage(
            userId: 'user-123',
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
            occurredAt: '2026-01-01T00:00:00+00:00',
        );
    }

    public function testImplementsMessageInterface(): void
    {
        $this->assertInstanceOf(MessageInterface::class, $this->message);
    }

    public function testGetUserId(): void
    {
        $this->assertSame('user-123', $this->message->getUserId());
    }

    public function testGetEmail(): void
    {
        $this->assertSame('john@example.com', $this->message->getEmail());
    }

    public function testGetFirstName(): void
    {
        $this->assertSame('John', $this->message->getFirstName());
    }

    public function testGetLastName(): void
    {
        $this->assertSame('Doe', $this->message->getLastName());
    }

    public function testGetOccurredAt(): void
    {
        $this->assertSame('2026-01-01T00:00:00+00:00', $this->message->getOccurredAt());
    }

    public function testToArrayReturnsCompletePayload(): void
    {
        $expected = [
            'userId' => 'user-123',
            'email' => 'john@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'occurredAt' => '2026-01-01T00:00:00+00:00',
        ];

        $this->assertSame($expected, $this->message->toArray());
    }

    public function testToArrayKeysAreExact(): void
    {
        $keys = array_keys($this->message->toArray());

        $this->assertSame(['userId', 'email', 'firstName', 'lastName', 'occurredAt'], $keys);
    }
}
