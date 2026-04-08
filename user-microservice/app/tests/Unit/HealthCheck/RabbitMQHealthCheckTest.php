<?php

declare(strict_types=1);

namespace App\Tests\Unit\HealthCheck;

use App\HealthCheck\RabbitMQHealthCheck;
use PHPUnit\Framework\TestCase;

class RabbitMQHealthCheckTest extends TestCase
{
    public function testGetNameReturnsRabbitmq(): void
    {
        $check = new RabbitMQHealthCheck('amqp://guest:guest@localhost:5672');

        $this->assertSame('rabbitmq', $check->getName());
    }

    public function testCheckReturnsUnknownWhenDsnIsEmpty(): void
    {
        $check = new RabbitMQHealthCheck('');

        $result = $check->check();

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('DSN not configured', $result['reason']);
    }

    public function testCheckReturnsUnhealthyWhenHostUnreachable(): void
    {
        $check = new RabbitMQHealthCheck('amqp://guest:guest@unreachable-host-xyz:5672');

        $result = $check->check();

        $this->assertSame('unhealthy', $result['status']);
    }
}
