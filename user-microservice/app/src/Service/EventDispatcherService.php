<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EventPublisherInterface;
use App\Entity\User;
use App\Enum\UserEventType;
use App\Message\UserCreatedMessage;
use App\Message\UserEventMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EventDispatcherService
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EventPublisherInterface $eventPublisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatchUserCreatedToRabbitMQ(User $user): void
    {
        $message = new UserCreatedMessage(
            userId: $user->getId()->toRfc4122(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            occurredAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );

        $this->messageBus->dispatch($message);

        $this->logger->info('UserCreatedMessage dispatched to RabbitMQ', [
            'userId' => $user->getId()->toRfc4122(),
        ]);
    }

    public function dispatchUserEventToKafka(UserEventType $eventType, User $user): void
    {
        $event = new UserEventMessage(
            eventType: $eventType,
            userId: $user->getId()->toRfc4122(),
            payload: $user->toArray(),
            occurredAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );

        $this->eventPublisher->publish(
            $user->getId()->toRfc4122(),
            $event->toArray()
        );
    }

    public function dispatchUserDeletedToKafka(string $userId, array $userData): void
    {
        $event = new UserEventMessage(
            eventType: UserEventType::Deleted,
            userId: $userId,
            payload: $userData,
            occurredAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );

        $this->eventPublisher->publish($userId, $event->toArray());
    }
}
