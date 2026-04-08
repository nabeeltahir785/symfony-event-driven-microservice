<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\UserCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NotificationHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserCreatedMessage $message): void
    {
        $this->logger->info('Processing welcome notification', [
            'userId' => $message->getUserId(),
            'email' => $message->getEmail(),
            'firstName' => $message->getFirstName(),
            'channel' => 'email',
        ]);

        $this->sendWelcomeEmail($message);
        $this->logNotificationDelivered($message);
    }

    private function sendWelcomeEmail(UserCreatedMessage $message): void
    {
        usleep(200_000);

        $this->logger->info('Welcome email dispatched', [
            'userId' => $message->getUserId(),
            'to' => $message->getEmail(),
            'subject' => sprintf('Welcome, %s!', $message->getFirstName()),
            'template' => 'emails/welcome.html.twig',
            'status' => 'sent',
        ]);
    }

    private function logNotificationDelivered(UserCreatedMessage $message): void
    {
        $this->logger->info('Notification pipeline completed', [
            'userId' => $message->getUserId(),
            'email' => $message->getEmail(),
            'occurredAt' => $message->getOccurredAt(),
            'processedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'channels' => ['email'],
            'result' => 'delivered',
        ]);
    }
}
