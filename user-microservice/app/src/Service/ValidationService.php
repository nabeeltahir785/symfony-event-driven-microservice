<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validate(object $dto): void
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
}
