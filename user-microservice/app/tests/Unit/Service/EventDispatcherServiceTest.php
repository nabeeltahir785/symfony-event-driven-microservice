<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Contract\EventPublisherInterface;
use App\Entity\User;
use App\Enum\UserEventType;
use App\Service\EventDispatcherService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class EventDispatcherServiceTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private EventPublisherInterface&MockObject $eventPublisher;
    private LoggerInterface&MockObject $logger;
    private EventDispatcherService $service;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->eventPublisher = $this->createMock(EventPublisherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new EventDispatcherService(
            $this->messageBus,
            $this->eventPublisher,
            $this->logger,
        );
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');

        return $user;
    }

    public function testDispatchUserCreatedToRabbitMQDispatchesMessage(): void
    {
        $user = $this->createUser();

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->service->dispatchUserCreatedToRabbitMQ($user);
    }

    public function testDispatchUserCreatedToRabbitMQContainsCorrectUserId(): void
    {
        $user = $this->createUser();

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($user) {
                return $message->getUserId() === $user->getId()->toRfc4122()
                    && $message->getEmail() === 'test@example.com'
                    && $message->getFirstName() === 'John'
                    && $message->getLastName() === 'Doe';
            }))
            ->willReturnCallback(fn($m) => new Envelope($m));

        $this->service->dispatchUserCreatedToRabbitMQ($user);
    }

    public function testDispatchUserEventToKafkaPublishesWithCorrectKey(): void
    {
        $user = $this->createUser();

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish')
            ->with(
                $user->getId()->toRfc4122(),
                $this->callback(function (array $payload) {
                    return $payload['eventType'] === UserEventType::Created->value
                        && $payload['userId'] === $payload['payload']['id'];
                })
            );

        $this->service->dispatchUserEventToKafka(UserEventType::Created, $user);
    }

    public function testDispatchUserEventToKafkaIncludesUserPayload(): void
    {
        $user = $this->createUser();

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish')
            ->with(
                $this->anything(),
                $this->callback(function (array $payload) {
                    return isset($payload['payload']['email'])
                        && $payload['payload']['email'] === 'test@example.com';
                })
            );

        $this->service->dispatchUserEventToKafka(UserEventType::Updated, $user);
    }

    public function testDispatchUserDeletedToKafkaUsesCorrectEventType(): void
    {
        $userData = ['id' => 'user-del', 'email' => 'deleted@test.com'];

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish')
            ->with(
                'user-del',
                $this->callback(function (array $payload) {
                    return $payload['eventType'] === UserEventType::Deleted->value;
                })
            );

        $this->service->dispatchUserDeletedToKafka('user-del', $userData);
    }

    public function testDispatchUserDeletedToKafkaIncludesUserData(): void
    {
        $userData = ['id' => 'user-del', 'email' => 'deleted@test.com'];

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish')
            ->with(
                $this->anything(),
                $this->callback(function (array $payload) use ($userData) {
                    return $payload['payload'] === $userData;
                })
            );

        $this->service->dispatchUserDeletedToKafka('user-del', $userData);
    }
}
