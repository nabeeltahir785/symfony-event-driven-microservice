<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateUserDTO;
use App\DTO\UpdateUserDTO;
use App\Entity\User;
use App\Enum\UserEventType;
use App\Repository\UserRepository;
use App\Service\EventDispatcherService;
use App\Service\UserService;
use App\Service\ValidationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private ValidationService&MockObject $validationService;
    private EventDispatcherService&MockObject $eventDispatcher;
    private LoggerInterface&MockObject $logger;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->validationService = $this->createMock(ValidationService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new UserService(
            $this->userRepository,
            $this->validationService,
            $this->eventDispatcher,
            $this->logger,
        );
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('John');
        $user->setLastName('Doe');

        return $user;
    }

    public function testCreateUserValidatesDTO(): void
    {
        $dto = new CreateUserDTO('new@test.com', 'New', 'User');

        $this->validationService
            ->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('save');

        $this->service->createUser($dto);
    }

    public function testCreateUserThrowsConflictOnDuplicateEmail(): void
    {
        $dto = new CreateUserDTO('existing@test.com', 'New', 'User');
        $existingUser = $this->createUser('existing@test.com');

        $this->userRepository
            ->method('findByEmail')
            ->with('existing@test.com')
            ->willReturn($existingUser);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->createUser($dto);
    }

    public function testCreateUserPersistsEntity(): void
    {
        $dto = new CreateUserDTO('new@test.com', 'New', 'User');

        $this->userRepository->method('findByEmail')->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));

        $this->service->createUser($dto);
    }

    public function testCreateUserDispatchesEvents(): void
    {
        $dto = new CreateUserDTO('new@test.com', 'New', 'User');

        $this->userRepository->method('findByEmail')->willReturn(null);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatchUserCreatedToRabbitMQ')
            ->with($this->isInstanceOf(User::class));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatchUserEventToKafka')
            ->with(UserEventType::Created, $this->isInstanceOf(User::class));

        $this->service->createUser($dto);
    }

    public function testCreateUserReturnsUserWithCorrectFields(): void
    {
        $dto = new CreateUserDTO('new@test.com', 'New', 'User');

        $this->userRepository->method('findByEmail')->willReturn(null);

        $result = $this->service->createUser($dto);

        $this->assertSame('new@test.com', $result->getEmail());
        $this->assertSame('New', $result->getFirstName());
        $this->assertSame('User', $result->getLastName());
    }

    public function testUpdateUserValidatesDTO(): void
    {
        $dto = new UpdateUserDTO(firstName: 'Updated');
        $user = $this->createUser();

        $this->validationService
            ->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->userRepository->method('find')->willReturn($user);

        $this->service->updateUser($user->getId()->toRfc4122(), $dto);
    }

    public function testUpdateUserThrowsNotFoundForMissingUser(): void
    {
        $dto = new UpdateUserDTO(firstName: 'Updated');

        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->updateUser('nonexistent-id', $dto);
    }

    public function testUpdateUserThrowsUnprocessableWhenNoChanges(): void
    {
        $dto = new UpdateUserDTO();
        $user = $this->createUser();

        $this->userRepository->method('find')->willReturn($user);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('No fields provided');

        $this->service->updateUser($user->getId()->toRfc4122(), $dto);
    }

    public function testUpdateUserThrowsConflictOnDuplicateEmail(): void
    {
        $dto = new UpdateUserDTO(email: 'taken@test.com');
        $user = $this->createUser();
        $otherUser = $this->createUser('taken@test.com');

        $this->userRepository->method('find')->willReturn($user);
        $this->userRepository->method('findByEmail')->willReturn($otherUser);

        $this->expectException(ConflictHttpException::class);

        $this->service->updateUser($user->getId()->toRfc4122(), $dto);
    }

    public function testUpdateUserAllowsSameEmailForSameUser(): void
    {
        $user = $this->createUser('same@test.com');
        $dto = new UpdateUserDTO(email: 'same@test.com');

        $this->userRepository->method('find')->willReturn($user);
        $this->userRepository->method('findByEmail')->willReturn($user);

        $result = $this->service->updateUser($user->getId()->toRfc4122(), $dto);

        $this->assertSame('same@test.com', $result->getEmail());
    }

    public function testUpdateUserAppliesPartialChanges(): void
    {
        $user = $this->createUser();
        $dto = new UpdateUserDTO(firstName: 'Updated');

        $this->userRepository->method('find')->willReturn($user);

        $result = $this->service->updateUser($user->getId()->toRfc4122(), $dto);

        $this->assertSame('Updated', $result->getFirstName());
        $this->assertSame('Doe', $result->getLastName());
    }

    public function testUpdateUserDispatchesKafkaEvent(): void
    {
        $user = $this->createUser();
        $dto = new UpdateUserDTO(lastName: 'NewLast');

        $this->userRepository->method('find')->willReturn($user);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatchUserEventToKafka')
            ->with(UserEventType::Updated, $this->isInstanceOf(User::class));

        $this->service->updateUser($user->getId()->toRfc4122(), $dto);
    }

    public function testDeleteUserThrowsNotFoundForMissingUser(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->deleteUser('missing-id');
    }

    public function testDeleteUserRemovesEntity(): void
    {
        $user = $this->createUser();

        $this->userRepository->method('find')->willReturn($user);

        $this->userRepository
            ->expects($this->once())
            ->method('remove')
            ->with($user);

        $this->service->deleteUser($user->getId()->toRfc4122());
    }

    public function testDeleteUserDispatchesKafkaDeletedEvent(): void
    {
        $user = $this->createUser();
        $userId = $user->getId()->toRfc4122();

        $this->userRepository->method('find')->willReturn($user);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatchUserDeletedToKafka')
            ->with($userId, $this->isType('array'));

        $this->service->deleteUser($userId);
    }

    public function testGetUserReturnsFoundUser(): void
    {
        $user = $this->createUser();

        $this->userRepository->method('find')->willReturn($user);

        $result = $this->service->getUser($user->getId()->toRfc4122());

        $this->assertSame($user, $result);
    }

    public function testGetUserThrowsNotFoundForMissingUser(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->getUser('nonexistent');
    }

    public function testListUsersNormalizesPageMinimum(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findAllPaginated')
            ->with(1, 10)
            ->willReturn(['items' => [], 'total' => 0, 'page' => 1, 'limit' => 10, 'pages' => 0]);

        $this->service->listUsers(0, 10);
    }

    public function testListUsersNormalizesLimitMaximum(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findAllPaginated')
            ->with(1, 100)
            ->willReturn(['items' => [], 'total' => 0, 'page' => 1, 'limit' => 100, 'pages' => 0]);

        $this->service->listUsers(1, 200);
    }

    public function testListUsersNormalizesLimitMinimum(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findAllPaginated')
            ->with(1, 1)
            ->willReturn(['items' => [], 'total' => 0, 'page' => 1, 'limit' => 1, 'pages' => 0]);

        $this->service->listUsers(1, -5);
    }

    public function testListUsersReturnsRepositoryResult(): void
    {
        $expected = [
            'items' => [$this->createUser()],
            'total' => 1,
            'page' => 1,
            'limit' => 10,
            'pages' => 1,
        ];

        $this->userRepository
            ->method('findAllPaginated')
            ->willReturn($expected);

        $result = $this->service->listUsers();

        $this->assertSame($expected, $result);
    }
}
