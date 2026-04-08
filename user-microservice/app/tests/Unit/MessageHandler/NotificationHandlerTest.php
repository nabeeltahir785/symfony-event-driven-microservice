<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\UserCreatedMessage;
use App\MessageHandler\NotificationHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationHandlerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private NotificationHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new NotificationHandler($this->logger);
    }

    private function createMessage(): UserCreatedMessage
    {
        return new UserCreatedMessage(
            userId: 'user-789',
            email: 'handler@test.com',
            firstName: 'Handler',
            lastName: 'Test',
            occurredAt: '2026-01-01T00:00:00+00:00',
        );
    }

    public function testInvokeLogsProcessingNotification(): void
    {
        $this->logger
            ->expects($this->exactly(3))
            ->method('info');

        ($this->handler)($this->createMessage());
    }

    public function testInvokeLogsWithCorrectUserId(): void
    {
        $message = $this->createMessage();

        $this->logger
            ->expects($this->exactly(3))
            ->method('info')
            ->with(
                $this->anything(),
                $this->callback(function (array $context) {
                    return isset($context['userId']) && $context['userId'] === 'user-789';
                })
            );

        ($this->handler)($message);
    }

    public function testInvokeIsCallable(): void
    {
        $this->assertTrue(is_callable($this->handler));
    }
}
