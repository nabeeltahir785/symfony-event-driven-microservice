<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\UserServiceInterface;
use App\DTO\CreateUserDTO;
use App\DTO\UpdateUserDTO;
use App\DTO\UserResponseDTO;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Users', description: 'User management endpoints')]
#[Route('/api/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {
    }

    #[OA\Get(
        summary: 'List all users (paginated)',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated user list'),
        ]
    )]
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

    #[OA\Get(
        summary: 'Get a single user by ID',
        responses: [
            new OA\Response(response: 200, description: 'User details'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    #[Route('/{id}', name: 'user_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        return $this->json([
            'data' => UserResponseDTO::fromEntity($user)->toArray(),
        ]);
    }

    #[OA\Post(
        summary: 'Create a new user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'firstName', 'lastName'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created'),
            new OA\Response(response: 409, description: 'Email already exists'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
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

    #[OA\Put(
        summary: 'Update an existing user',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', nullable: true),
                    new OA\Property(property: 'firstName', type: 'string', nullable: true),
                    new OA\Property(property: 'lastName', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 409, description: 'Email already exists'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
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

    #[OA\Delete(
        summary: 'Delete a user',
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    #[Route('/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->userService->deleteUser($id);

        return $this->json([
            'message' => 'User deleted successfully. Event dispatched to Kafka.',
        ], Response::HTTP_NO_CONTENT);
    }
}
