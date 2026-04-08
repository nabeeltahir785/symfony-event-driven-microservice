<?php

declare(strict_types=1);

namespace App\Contract;

use App\DTO\CreateUserDTO;
use App\DTO\UpdateUserDTO;
use App\Entity\User;

interface UserServiceInterface
{
    public function createUser(CreateUserDTO $dto): User;

    public function updateUser(string $id, UpdateUserDTO $dto): User;

    public function deleteUser(string $id): void;

    public function getUser(string $id): User;

    public function listUsers(int $page = 1, int $limit = 10): array;
}
