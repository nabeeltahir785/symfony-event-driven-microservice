<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ApiUser;
use App\Repository\ApiUserRepository;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly ApiUserRepository $apiUserRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw new UnprocessableEntityHttpException('Email and password are required.');
        }

        if (strlen($password) < 6) {
            throw new UnprocessableEntityHttpException('Password must be at least 6 characters.');
        }

        if ($this->apiUserRepository->findByEmail($email)) {
            throw new ConflictHttpException(
                sprintf('An API user with email "%s" already exists.', $email)
            );
        }

        $apiUser = new ApiUser();
        $apiUser->setEmail($email);
        $apiUser->setPassword($this->passwordHasher->hashPassword($apiUser, $password));

        $this->apiUserRepository->save($apiUser);

        return $this->json([
            'message' => 'API user registered successfully.',
            'userId' => $apiUser->getId()->toRfc4122(),
        ], Response::HTTP_CREATED);
    }
}
