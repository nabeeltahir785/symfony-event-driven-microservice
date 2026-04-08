<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CreateUserDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CreateUserDTOTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $dto = new CreateUserDTO(
            email: 'test@example.com',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->assertSame('test@example.com', $dto->email);
        $this->assertSame('John', $dto->firstName);
        $this->assertSame('Doe', $dto->lastName);
    }

    public function testFromRequestParsesValidJson(): void
    {
        $request = new Request(
            content: json_encode([
                'email' => 'john@test.com',
                'firstName' => 'John',
                'lastName' => 'Doe',
            ])
        );

        $dto = CreateUserDTO::fromRequest($request);

        $this->assertSame('john@test.com', $dto->email);
        $this->assertSame('John', $dto->firstName);
        $this->assertSame('Doe', $dto->lastName);
    }

    public function testFromRequestHandlesMissingFields(): void
    {
        $request = new Request(content: json_encode([]));

        $dto = CreateUserDTO::fromRequest($request);

        $this->assertSame('', $dto->email);
        $this->assertSame('', $dto->firstName);
        $this->assertSame('', $dto->lastName);
    }

    public function testFromRequestHandlesInvalidJson(): void
    {
        $request = new Request(content: 'not-json');

        $dto = CreateUserDTO::fromRequest($request);

        $this->assertSame('', $dto->email);
        $this->assertSame('', $dto->firstName);
        $this->assertSame('', $dto->lastName);
    }

    public function testFromRequestHandlesEmptyBody(): void
    {
        $request = new Request(content: '');

        $dto = CreateUserDTO::fromRequest($request);

        $this->assertSame('', $dto->email);
        $this->assertSame('', $dto->firstName);
        $this->assertSame('', $dto->lastName);
    }

    public function testFromRequestIgnoresExtraFields(): void
    {
        $request = new Request(
            content: json_encode([
                'email' => 'a@b.com',
                'firstName' => 'A',
                'lastName' => 'B',
                'age' => 30,
                'role' => 'admin',
            ])
        );

        $dto = CreateUserDTO::fromRequest($request);

        $this->assertSame('a@b.com', $dto->email);
        $this->assertSame('A', $dto->firstName);
        $this->assertSame('B', $dto->lastName);
    }

    public function testPropertiesAreReadonly(): void
    {
        $dto = new CreateUserDTO(
            email: 'readonly@test.com',
            firstName: 'Read',
            lastName: 'Only'
        );

        $reflection = new \ReflectionClass($dto);

        $this->assertTrue($reflection->getProperty('email')->isReadOnly());
        $this->assertTrue($reflection->getProperty('firstName')->isReadOnly());
        $this->assertTrue($reflection->getProperty('lastName')->isReadOnly());
    }
}
