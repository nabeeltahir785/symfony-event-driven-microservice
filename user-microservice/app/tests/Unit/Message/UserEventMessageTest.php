<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Contract\MessageInterface;
use App\Enum\UserEventType;
use App\Message\UserEventMessage;
use PHPUnit\Framework\TestCase;

class UserEventMessageTest extends TestCase
{
    private UserEventMessage $message;
    private array $payload;

    protected function setUp(): void
    {
        $this->payload = ['email' => 'test@example.com', 'firstName' => 'Test'];

        $this->message = new UserEventMessage(
            eventType: UserEventType::Created,
            userId: 'user-456',
            payload: $this->payload,
            occurredAt: '2026-06-15T12:00:00+00:00',
        );
    }

    public function testImplementsMessageInterface(): void
    {
        $this->assertInstanceOf(MessageInterface::class, $this->message);
    }

    public function testGetEventTypeReturnsEnum(): void
    {
        $this->assertSame(UserEventType::Created, $this->message->getEventType());
    }

    public function testGetUserId(): void
    {
        $this->assertSame('user-456', $this->message->getUserId());
    }

    public function testGetPayload(): void
    {
        $this->assertSame($this->payload, $this->message->getPayload());
    }

    public function testGetOccurredAt(): void
    {
        $this->assertSame('2026-06-15T12:00:00+00:00', $this->message->getOccurredAt());
    }

    public function testToArrayUsesEnumValue(): void
    {
        $expected = [
            'eventType' => 'user.created',
            'userId' => 'user-456',
            'payload' => $this->payload,
            'occurredAt' => '2026-06-15T12:00:00+00:00',
        ];

        $this->assertSame($expected, $this->message->toArray());
    }

    public function testEnumCreatedValue(): void
    {
        $this->assertSame('user.created', UserEventType::Created->value);
    }

    public function testEnumUpdatedValue(): void
    {
        $this->assertSame('user.updated', UserEventType::Updated->value);
    }

    public function testEnumDeletedValue(): void
    {
        $this->assertSame('user.deleted', UserEventType::Deleted->value);
    }

    public function testToArrayKeysAreExact(): void
    {
        $keys = array_keys($this->message->toArray());

        $this->assertSame(['eventType', 'userId', 'payload', 'occurredAt'], $keys);
    }

    public function testAllEventTypesAreBackedStrings(): void
    {
        $cases = UserEventType::cases();

        $this->assertCount(3, $cases);

        foreach ($cases as $case) {
            $this->assertIsString($case->value);
            $this->assertStringStartsWith('user.', $case->value);
        }
    }
}
