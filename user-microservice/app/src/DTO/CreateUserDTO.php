<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO
{
    use RequestParseTrait;

    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Please provide a valid email address.')]
        public readonly string $email,

        #[Assert\NotBlank(message: 'First name is required.')]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'First name must be at least {{ limit }} characters.',
            maxMessage: 'First name cannot exceed {{ limit }} characters.'
        )]
        public readonly string $firstName,

        #[Assert\NotBlank(message: 'Last name is required.')]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'Last name must be at least {{ limit }} characters.',
            maxMessage: 'Last name cannot exceed {{ limit }} characters.'
        )]
        public readonly string $lastName,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = self::parseJsonBody($request);

        return new self(
            email: $data['email'] ?? '',
            firstName: $data['firstName'] ?? '',
            lastName: $data['lastName'] ?? '',
        );
    }
}
