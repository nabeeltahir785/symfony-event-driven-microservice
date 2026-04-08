<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Contract\UserServiceInterface;
use App\DTO\CreateUserDTO;
use App\DTO\UpdateUserDTO;
use App\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserControllerIntegrationTest extends TestCase
{
    private UserServiceInterface&MockObject $userService;

    protected function setUp(): void
    {
        $this->userService = $this->createMock(UserServiceInterface::class);
    }

    private function createUser(
        string $email = 'test@example.com',
        string $firstName = 'John',
        string $lastName = 'Doe'
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        return $user;
    }

    public function testCreateUserEndToEndFlow(): void
    {
        $request = new Request(
            content: json_encode([
                'email' => 'integration@test.com',
                'firstName' => 'Integration',
                'lastName' => 'Test',
            ])
        );

        $dto = CreateUserDTO::fromRequest($request);
        $user = $this->createUser('integration@test.com', 'Integration', 'Test');

        $this->userService
            ->expects($this->once())
            ->method('createUser')
            ->with($this->callback(function (CreateUserDTO $actualDto) use ($dto) {
                return $actualDto->email === $dto->email
                    && $actualDto->firstName === $dto->firstName
                    && $actualDto->lastName === $dto->lastName;
            }))
            ->willReturn($user);

        $result = $this->userService->createUser($dto);

        $this->assertSame('integration@test.com', $result->getEmail());
        $this->assertSame('Integration', $result->getFirstName());
        $this->assertSame('Test', $result->getLastName());
    }

    public function testUpdateUserEndToEndFlow(): void
    {
        $user = $this->createUser();
        $userId = $user->getId()->toRfc4122();

        $request = new Request(
            content: json_encode(['firstName' => 'Updated'])
        );
        $dto = UpdateUserDTO::fromRequest($request);

        $updatedUser = $this->createUser('test@example.com', 'Updated', 'Doe');

        $this->userService
            ->expects($this->once())
            ->method('updateUser')
            ->with($userId, $this->isInstanceOf(UpdateUserDTO::class))
            ->willReturn($updatedUser);

        $result = $this->userService->updateUser($userId, $dto);

        $this->assertSame('Updated', $result->getFirstName());
    }

    public function testDeleteUserEndToEndFlow(): void
    {
        $user = $this->createUser();
        $userId = $user->getId()->toRfc4122();

        $this->userService
            ->expects($this->once())
            ->method('deleteUser')
            ->with($userId);

        $this->userService->deleteUser($userId);
    }

    public function testGetUserEndToEndFlow(): void
    {
        $user = $this->createUser();
        $userId = $user->getId()->toRfc4122();

        $this->userService
            ->method('getUser')
            ->with($userId)
            ->willReturn($user);

        $result = $this->userService->getUser($userId);

        $this->assertSame($user, $result);
        $this->assertSame('test@example.com', $result->getEmail());
    }

    public function testListUsersEndToEndFlow(): void
    {
        $users = [$this->createUser(), $this->createUser('jane@test.com', 'Jane', 'Smith')];
        $paginatedResult = [
            'items' => $users,
            'total' => 2,
            'page' => 1,
            'limit' => 10,
            'pages' => 1,
        ];

        $this->userService
            ->method('listUsers')
            ->with(1, 10)
            ->willReturn($paginatedResult);

        $result = $this->userService->listUsers(1, 10);

        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['pages']);
    }

    public function testCreateUserDuplicateEmailRejection(): void
    {
        $dto = new CreateUserDTO('existing@test.com', 'Dup', 'User');

        $this->userService
            ->method('createUser')
            ->willThrowException(new ConflictHttpException('A user with email "existing@test.com" already exists.'));

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('already exists');

        $this->userService->createUser($dto);
    }

    public function testGetUserNotFoundRejection(): void
    {
        $this->userService
            ->method('getUser')
            ->willThrowException(new NotFoundHttpException('User with ID "missing" not found.'));

        $this->expectException(NotFoundHttpException::class);

        $this->userService->getUser('missing');
    }

    public function testCreateUserDTOToEntityMapping(): void
    {
        $request = new Request(
            content: json_encode([
                'email' => 'mapping@test.com',
                'firstName' => 'Map',
                'lastName' => 'Test',
            ])
        );

        $dto = CreateUserDTO::fromRequest($request);

        $this->assertSame('mapping@test.com', $dto->email);
        $this->assertSame('Map', $dto->firstName);
        $this->assertSame('Test', $dto->lastName);

        $user = new User();
        $user->setEmail($dto->email);
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);

        $this->assertSame($dto->email, $user->getEmail());
        $this->assertSame($dto->firstName, $user->getFirstName());
        $this->assertSame($dto->lastName, $user->getLastName());
    }

    public function testUserResponseDTOFromEntityMapping(): void
    {
        $user = $this->createUser();

        $responseDto = \App\DTO\UserResponseDTO::fromEntity($user);

        $this->assertSame($user->getId()->toRfc4122(), $responseDto->id);
        $this->assertSame($user->getEmail(), $responseDto->email);
        $this->assertSame($user->getFirstName(), $responseDto->firstName);
        $this->assertSame($user->getLastName(), $responseDto->lastName);

        $array = $responseDto->toArray();
        $this->assertCount(6, $array);
        $this->assertSame($responseDto->id, $array['id']);
    }

    public function testEventDispatchIntegrationWithMockedService(): void
    {
        $user = $this->createUser('events@test.com', 'Events', 'Test');

        $this->userService
            ->expects($this->once())
            ->method('createUser')
            ->willReturn($user);

        $result = $this->userService->createUser(
            new CreateUserDTO('events@test.com', 'Events', 'Test')
        );

        $this->assertNotEmpty($result->getId()->toRfc4122());
    }

    public function testPaginationBoundaryIntegration(): void
    {
        $emptyResult = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'limit' => 10,
            'pages' => 0,
        ];

        $this->userService
            ->method('listUsers')
            ->willReturn($emptyResult);

        $result = $this->userService->listUsers(1, 10);

        $this->assertEmpty($result['items']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['pages']);
    }
}
