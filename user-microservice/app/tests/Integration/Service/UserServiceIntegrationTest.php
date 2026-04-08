<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Contract\EventPublisherInterface;
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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserServiceIntegrationTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private ValidatorInterface&MockObject $validator;
    private MessageBusInterface&MockObject $messageBus;
    private EventPublisherInterface&MockObject $eventPublisher;
    private LoggerInterface&MockObject $logger;
    private UserService $userService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->eventPublisher = $this->createMock(EventPublisherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $validationService = new ValidationService($this->validator);
        $eventDispatcher = new EventDispatcherService(
            $this->messageBus,
            $this->eventPublisher,
            $this->logger,
        );

        $this->userService = new UserService(
            $this->userRepository,
            $validationService,
            $eventDispatcher,
            $this->logger,
        );
    }

    private function setupValidValidation(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([]));
    }

    public function testCreateUserFullPipelineValidationToEvents(): void
    {
        $this->setupValidValidation();
        $this->userRepository->method('findByEmail')->willReturn(null);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn($m) => new Envelope($m));

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish');

        $dto = new CreateUserDTO('pipeline@test.com', 'Pipeline', 'Test');
        $user = $this->userService->createUser($dto);

        $this->assertSame('pipeline@test.com', $user->getEmail());
        $this->assertSame('Pipeline', $user->getFirstName());
        $this->assertSame('Test', $user->getLastName());
    }

    public function testUpdateUserFullPipelineWithPartialUpdate(): void
    {
        $this->setupValidValidation();

        $existingUser = new User();
        $existingUser->setEmail('existing@test.com');
        $existingUser->setFirstName('Original');
        $existingUser->setLastName('Name');

        $this->userRepository->method('find')->willReturn($existingUser);

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish');

        $dto = new UpdateUserDTO(firstName: 'Updated');
        $result = $this->userService->updateUser(
            $existingUser->getId()->toRfc4122(),
            $dto
        );

        $this->assertSame('Updated', $result->getFirstName());
        $this->assertSame('Name', $result->getLastName());
        $this->assertSame('existing@test.com', $result->getEmail());
    }

    public function testDeleteUserFullPipelineRemoveAndPublish(): void
    {
        $user = new User();
        $user->setEmail('delete@test.com');
        $user->setFirstName('Delete');
        $user->setLastName('Me');
        $userId = $user->getId()->toRfc4122();

        $this->userRepository->method('find')->willReturn($user);

        $this->userRepository
            ->expects($this->once())
            ->method('remove')
            ->with($user);

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish')
            ->with(
                $userId,
                $this->callback(function (array $payload) {
                    return $payload['eventType'] === UserEventType::Deleted->value
                        && isset($payload['payload']['email']);
                })
            );

        $this->userService->deleteUser($userId);
    }

    public function testCreateUserValidationFailureStopsExecution(): void
    {
        $violation = new \Symfony\Component\Validator\ConstraintViolation(
            'Email is required.',
            null,
            [],
            null,
            'email',
            null
        );
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->eventPublisher
            ->expects($this->never())
            ->method('publish');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException::class);

        $dto = new CreateUserDTO('', 'Invalid', 'User');
        $this->userService->createUser($dto);
    }

    public function testCreateUserDuplicateEmailStopsExecution(): void
    {
        $this->setupValidValidation();

        $existingUser = new User();
        $existingUser->setEmail('dup@test.com');
        $existingUser->setFirstName('Existing');
        $existingUser->setLastName('User');

        $this->userRepository
            ->method('findByEmail')
            ->willReturn($existingUser);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);

        $dto = new CreateUserDTO('dup@test.com', 'Dup', 'User');
        $this->userService->createUser($dto);
    }

    public function testUpdateUserWithFullFieldUpdate(): void
    {
        $this->setupValidValidation();

        $existingUser = new User();
        $existingUser->setEmail('old@test.com');
        $existingUser->setFirstName('Old');
        $existingUser->setLastName('Name');

        $this->userRepository->method('find')->willReturn($existingUser);
        $this->userRepository->method('findByEmail')->willReturn(null);

        $dto = new UpdateUserDTO(
            email: 'new@test.com',
            firstName: 'New',
            lastName: 'Surname'
        );

        $result = $this->userService->updateUser(
            $existingUser->getId()->toRfc4122(),
            $dto
        );

        $this->assertSame('new@test.com', $result->getEmail());
        $this->assertSame('New', $result->getFirstName());
        $this->assertSame('Surname', $result->getLastName());
    }
}
