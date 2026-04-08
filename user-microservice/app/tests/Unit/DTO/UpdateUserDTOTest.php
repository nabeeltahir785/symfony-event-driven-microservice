<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\UpdateUserDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class UpdateUserDTOTest extends TestCase
{
    public function testConstructorDefaultsToNull(): void
    {
        $dto = new UpdateUserDTO();

        $this->assertNull($dto->email);
        $this->assertNull($dto->firstName);
        $this->assertNull($dto->lastName);
    }

    public function testConstructorSetsProvidedValues(): void
    {
        $dto = new UpdateUserDTO(
            email: 'update@test.com',
            firstName: 'Updated',
            lastName: 'User'
        );

        $this->assertSame('update@test.com', $dto->email);
        $this->assertSame('Updated', $dto->firstName);
        $this->assertSame('User', $dto->lastName);
    }

    public function testHasChangesReturnsFalseWhenAllNull(): void
    {
        $dto = new UpdateUserDTO();

        $this->assertFalse($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueWhenEmailSet(): void
    {
        $dto = new UpdateUserDTO(email: 'new@email.com');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueWhenFirstNameSet(): void
    {
        $dto = new UpdateUserDTO(firstName: 'New');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueWhenLastNameSet(): void
    {
        $dto = new UpdateUserDTO(lastName: 'Name');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueWhenAllFieldsSet(): void
    {
        $dto = new UpdateUserDTO(
            email: 'all@fields.com',
            firstName: 'All',
            lastName: 'Fields'
        );

        $this->assertTrue($dto->hasChanges());
    }

    public function testFromRequestParsesPartialPayload(): void
    {
        $request = new Request(
            content: json_encode(['firstName' => 'OnlyFirst'])
        );

        $dto = UpdateUserDTO::fromRequest($request);

        $this->assertNull($dto->email);
        $this->assertSame('OnlyFirst', $dto->firstName);
        $this->assertNull($dto->lastName);
    }

    public function testFromRequestParsesFullPayload(): void
    {
        $request = new Request(
            content: json_encode([
                'email' => 'full@test.com',
                'firstName' => 'Full',
                'lastName' => 'Payload',
            ])
        );

        $dto = UpdateUserDTO::fromRequest($request);

        $this->assertSame('full@test.com', $dto->email);
        $this->assertSame('Full', $dto->firstName);
        $this->assertSame('Payload', $dto->lastName);
    }

    public function testFromRequestHandlesEmptyJson(): void
    {
        $request = new Request(content: json_encode([]));

        $dto = UpdateUserDTO::fromRequest($request);

        $this->assertNull($dto->email);
        $this->assertNull($dto->firstName);
        $this->assertNull($dto->lastName);
        $this->assertFalse($dto->hasChanges());
    }

    public function testFromRequestHandlesInvalidJson(): void
    {
        $request = new Request(content: 'invalid');

        $dto = UpdateUserDTO::fromRequest($request);

        $this->assertNull($dto->email);
        $this->assertNull($dto->firstName);
        $this->assertNull($dto->lastName);
    }
}
