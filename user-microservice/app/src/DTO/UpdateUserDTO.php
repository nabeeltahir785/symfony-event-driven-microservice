<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserDTO
{
    use RequestParseTrait;

    public function __construct(
        #[Assert\Email(message: 'Please provide a valid email address.')]
        public readonly ?string $email = null,

        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'First name must be at least {{ limit }} characters.',
            maxMessage: 'First name cannot exceed {{ limit }} characters.'
        )]
        public readonly ?string $firstName = null,

        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'Last name must be at least {{ limit }} characters.',
            maxMessage: 'Last name cannot exceed {{ limit }} characters.'
        )]
        public readonly ?string $lastName = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = self::parseJsonBody($request);

        return new self(
            email: $data['email'] ?? null,
            firstName: $data['firstName'] ?? null,
            lastName: $data['lastName'] ?? null,
        );
    }

    public function hasChanges(): bool
    {
        return $this->email !== null
            || $this->firstName !== null
            || $this->lastName !== null;
    }
}
