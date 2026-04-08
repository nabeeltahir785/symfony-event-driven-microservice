<?php

declare(strict_types=1);

namespace App\Tests\Unit\HealthCheck;

use App\HealthCheck\DatabaseHealthCheck;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatabaseHealthCheckTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DatabaseHealthCheck $healthCheck;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->healthCheck = new DatabaseHealthCheck($this->entityManager);
    }

    public function testGetNameReturnsDatabase(): void
    {
        $this->assertSame('database', $this->healthCheck->getName());
    }

    public function testCheckReturnsHealthyOnSuccessfulQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createMock(\Doctrine\DBAL\Result::class));

        $this->entityManager->method('getConnection')->willReturn($connection);

        $result = $this->healthCheck->check();

        $this->assertSame('healthy', $result['status']);
        $this->assertSame('pdo_pgsql', $result['driver']);
    }

    public function testCheckReturnsUnhealthyOnException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \Exception('Connection refused'));

        $this->entityManager->method('getConnection')->willReturn($connection);

        $result = $this->healthCheck->check();

        $this->assertSame('unhealthy', $result['status']);
        $this->assertSame('Connection refused', $result['error']);
    }
}
