<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Contract\HealthCheckInterface;
use App\Controller\HealthController;
use PHPUnit\Framework\TestCase;

class HealthControllerTest extends TestCase
{
    public function testCheckReturnsHealthyWhenAllChecksPass(): void
    {
        $checkA = $this->createMock(HealthCheckInterface::class);
        $checkA->method('getName')->willReturn('database');
        $checkA->method('check')->willReturn(['status' => 'healthy', 'driver' => 'pdo_pgsql']);

        $checkB = $this->createMock(HealthCheckInterface::class);
        $checkB->method('getName')->willReturn('rabbitmq');
        $checkB->method('check')->willReturn(['status' => 'healthy', 'host' => 'localhost']);

        $controller = new HealthController([$checkA, $checkB]);

        $response = $controller->check();

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('healthy', $body['status']);
        $this->assertSame('user-microservice', $body['service']);
        $this->assertArrayHasKey('timestamp', $body);
        $this->assertArrayHasKey('database', $body['checks']);
        $this->assertArrayHasKey('rabbitmq', $body['checks']);
    }

    public function testCheckReturnsDegradedWhenAnyCheckFails(): void
    {
        $healthyCheck = $this->createMock(HealthCheckInterface::class);
        $healthyCheck->method('getName')->willReturn('database');
        $healthyCheck->method('check')->willReturn(['status' => 'healthy']);

        $unhealthyCheck = $this->createMock(HealthCheckInterface::class);
        $unhealthyCheck->method('getName')->willReturn('kafka');
        $unhealthyCheck->method('check')->willReturn(['status' => 'unhealthy', 'error' => 'timeout']);

        $controller = new HealthController([$healthyCheck, $unhealthyCheck]);

        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('degraded', $body['status']);
    }

    public function testCheckReturnsHealthyWithNoChecks(): void
    {
        $controller = new HealthController([]);

        $response = $controller->check();

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('healthy', $body['status']);
        $this->assertEmpty($body['checks']);
    }

    public function testCheckIncludesTimestampInAtomFormat(): void
    {
        $controller = new HealthController([]);

        $response = $controller->check();

        $body = json_decode($response->getContent(), true);
        $timestamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $body['timestamp']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $timestamp);
    }

    public function testCheckRunsAllHealthChecks(): void
    {
        $checkA = $this->createMock(HealthCheckInterface::class);
        $checkA->expects($this->once())->method('getName')->willReturn('a');
        $checkA->expects($this->once())->method('check')->willReturn(['status' => 'healthy']);

        $checkB = $this->createMock(HealthCheckInterface::class);
        $checkB->expects($this->once())->method('getName')->willReturn('b');
        $checkB->expects($this->once())->method('check')->willReturn(['status' => 'healthy']);

        $checkC = $this->createMock(HealthCheckInterface::class);
        $checkC->expects($this->once())->method('getName')->willReturn('c');
        $checkC->expects($this->once())->method('check')->willReturn(['status' => 'healthy']);

        $controller = new HealthController([$checkA, $checkB, $checkC]);
        $controller->check();
    }
}
