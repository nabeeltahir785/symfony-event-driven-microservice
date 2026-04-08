<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateUserDTO;
use App\DTO\UpdateUserDTO;
use App\Entity\User;
use App\Message\UserCreatedMessage;
use App\Message\UserEventMessage;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly KafkaProducerService $kafkaProducer,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createUser(CreateUserDTO $dto): User
    {
        $this->validate($dto);

        if ($this->userRepository->findByEmail($dto->email)) {
            throw new ConflictHttpException(
                sprintf('A user with email "%s" already exists.', $dto->email)
            );
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);

        $this->userRepository->save($user);

        $this->logger->info('User created', [
            'userId' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
        ]);

        $this->dispatchUserCreatedToRabbitMQ($user);
        $this->dispatchEventToKafka(UserEventMessage::TYPE_CREATED, $user);

        return $user;
    }

    public function updateUser(string $id, UpdateUserDTO $dto): User
    {
        $this->validate($dto);

        $user = $this->findUserOrFail($id);

        if (!$dto->hasChanges()) {
            throw new UnprocessableEntityHttpException('No fields provided for update.');
        }

        if ($dto->email !== null) {
            $existing = $this->userRepository->findByEmail($dto->email);
            if ($existing && $existing->getId()->toRfc4122() !== $id) {
                throw new ConflictHttpException(
                    sprintf('A user with email "%s" already exists.', $dto->email)
                );
            }
            $user->setEmail($dto->email);
        }

        if ($dto->firstName !== null) {
            $user->setFirstName($dto->firstName);
        }

        if ($dto->lastName !== null) {
            $user->setLastName($dto->lastName);
        }

        $this->userRepository->save($user);

        $this->logger->info('User updated', [
            'userId' => $user->getId()->toRfc4122(),
        ]);

        $this->dispatchEventToKafka(UserEventMessage::TYPE_UPDATED, $user);

        return $user;
    }

    public function deleteUser(string $id): void
    {
        $user = $this->findUserOrFail($id);
        $userData = $user->toArray();

        $this->userRepository->remove($user);

        $this->logger->info('User deleted', ['userId' => $id]);

        $event = new UserEventMessage(
            eventType: UserEventMessage::TYPE_DELETED,
            userId: $id,
            payload: $userData,
            occurredAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );

        $this->kafkaProducer->produce($id, $event->toArray());
    }

    public function getUser(string $id): User
    {
        return $this->findUserOrFail($id);
    }

    /**
     * @return array{items: array, total: int, page: int, limit: int, pages: int}
     */
    public function listUsers(int $page = 1, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        return $this->userRepository->findAllPaginated($page, $limit);
    }

    private function findUserOrFail(string $id): User
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new NotFoundHttpException(
                sprintf('User with ID "%s" not found.', $id)
            );
        }

        return $user;
    }

    private function validate(object $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            throw new UnprocessableEntityHttpException(
                json_encode(['validation_errors' => $errors])
            );
        }
    }

    private function dispatchUserCreatedToRabbitMQ(User $user): void
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

    private function dispatchEventToKafka(string $eventType, User $user): void
    {
        $event = new UserEventMessage(
            eventType: $eventType,
            userId: $user->getId()->toRfc4122(),
            payload: $user->toArray(),
            occurredAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );

        $this->kafkaProducer->produce(
            $user->getId()->toRfc4122(),
            $event->toArray()
        );
    }
}
