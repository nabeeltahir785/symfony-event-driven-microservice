<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\UserServiceInterface;
use App\DTO\CreateUserDTO;
use App\DTO\UpdateUserDTO;
use App\Entity\User;
use App\Enum\UserEventType;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ValidationService $validationService,
        private readonly EventDispatcherService $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createUser(CreateUserDTO $dto): User
    {
        $this->validationService->validate($dto);

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

        $this->eventDispatcher->dispatchUserCreatedToRabbitMQ($user);
        $this->eventDispatcher->dispatchUserEventToKafka(UserEventType::Created, $user);

        return $user;
    }

    public function updateUser(string $id, UpdateUserDTO $dto): User
    {
        $this->validationService->validate($dto);

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

        $this->eventDispatcher->dispatchUserEventToKafka(UserEventType::Updated, $user);

        return $user;
    }

    public function deleteUser(string $id): void
    {
        $user = $this->findUserOrFail($id);
        $userData = $user->toArray();

        $this->userRepository->remove($user);

        $this->logger->info('User deleted', ['userId' => $id]);

        $this->eventDispatcher->dispatchUserDeletedToKafka($id, $userData);
    }

    public function getUser(string $id): User
    {
        return $this->findUserOrFail($id);
    }

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
}
