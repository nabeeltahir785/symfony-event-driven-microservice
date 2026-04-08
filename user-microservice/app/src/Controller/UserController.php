<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateUserDTO;
use App\DTO\UpdateUserDTO;
use App\DTO\UserResponseDTO;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    #[Route('', name: 'user_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $result = $this->userService->listUsers($page, $limit);

        return $this->json([
            'data' => UserResponseDTO::collectionToArray($result['items']),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit'],
                'pages' => $result['pages'],
            ],
        ]);
    }

    #[Route('/{id}', name: 'user_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        return $this->json([
            'data' => UserResponseDTO::fromEntity($user)->toArray(),
        ]);
    }

    #[Route('', name: 'user_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $dto = CreateUserDTO::fromRequest($request);
        $user = $this->userService->createUser($dto);

        return $this->json([
            'data' => UserResponseDTO::fromEntity($user)->toArray(),
            'message' => 'User created successfully. Events dispatched to RabbitMQ and Kafka.',
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'user_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $dto = UpdateUserDTO::fromRequest($request);
        $user = $this->userService->updateUser($id, $dto);

        return $this->json([
            'data' => UserResponseDTO::fromEntity($user)->toArray(),
            'message' => 'User updated successfully. Event dispatched to Kafka.',
        ]);
    }

    #[Route('/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->userService->deleteUser($id);

        return $this->json([
            'message' => 'User deleted successfully. Event dispatched to Kafka.',
        ], Response::HTTP_NO_CONTENT);
    }
}
