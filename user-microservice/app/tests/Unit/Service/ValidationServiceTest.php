<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ValidationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationServiceTest extends TestCase
{
    private ValidatorInterface&MockObject $validator;
    private ValidationService $service;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->service = new ValidationService($this->validator);
    }

    public function testValidatePassesWithNoViolations(): void
    {
        $dto = new \stdClass();
        $violations = new ConstraintViolationList([]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->service->validate($dto);

        $this->assertTrue(true);
    }

    public function testValidateThrowsUnprocessableEntityOnViolations(): void
    {
        $dto = new \stdClass();
        $violation = new ConstraintViolation(
            'Email is required.',
            null,
            [],
            null,
            'email',
            null
        );
        $violations = new ConstraintViolationList([$violation]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service->validate($dto);
    }

    public function testValidateExceptionContainsValidationErrors(): void
    {
        $dto = new \stdClass();
        $violation = new ConstraintViolation(
            'Email is required.',
            null,
            [],
            null,
            'email',
            null
        );
        $violations = new ConstraintViolationList([$violation]);

        $this->validator
            ->method('validate')
            ->willReturn($violations);

        try {
            $this->service->validate($dto);
            $this->fail('Expected UnprocessableEntityHttpException');
        } catch (UnprocessableEntityHttpException $e) {
            $decoded = json_decode($e->getMessage(), true);
            $this->assertArrayHasKey('validation_errors', $decoded);
            $this->assertArrayHasKey('email', $decoded['validation_errors']);
            $this->assertContains('Email is required.', $decoded['validation_errors']['email']);
        }
    }

    public function testValidateGroupsMultipleViolationsByProperty(): void
    {
        $dto = new \stdClass();
        $violationA = new ConstraintViolation('Required.', null, [], null, 'email', null);
        $violationB = new ConstraintViolation('Invalid format.', null, [], null, 'email', null);
        $violationC = new ConstraintViolation('Too short.', null, [], null, 'firstName', null);
        $violations = new ConstraintViolationList([$violationA, $violationB, $violationC]);

        $this->validator
            ->method('validate')
            ->willReturn($violations);

        try {
            $this->service->validate($dto);
            $this->fail('Expected UnprocessableEntityHttpException');
        } catch (UnprocessableEntityHttpException $e) {
            $decoded = json_decode($e->getMessage(), true);
            $errors = $decoded['validation_errors'];

            $this->assertCount(2, $errors['email']);
            $this->assertCount(1, $errors['firstName']);
        }
    }
}
