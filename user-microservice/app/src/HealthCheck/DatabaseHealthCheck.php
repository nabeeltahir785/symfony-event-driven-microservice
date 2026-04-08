<?php

declare(strict_types=1);

namespace App\HealthCheck;

use App\Contract\HealthCheckInterface;
use Doctrine\ORM\EntityManagerInterface;

class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'database';
    }

    public function check(): array
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');
            return ['status' => 'healthy', 'driver' => 'pdo_pgsql'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }
}
