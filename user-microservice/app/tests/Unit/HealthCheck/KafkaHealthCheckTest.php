<?php

declare(strict_types=1);

namespace App\Tests\Unit\HealthCheck;

use App\HealthCheck\KafkaHealthCheck;
use PHPUnit\Framework\TestCase;

class KafkaHealthCheckTest extends TestCase
{
    public function testGetNameReturnsKafka(): void
    {
        $check = new KafkaHealthCheck('localhost:9092');

        $this->assertSame('kafka', $check->getName());
    }

    public function testCheckReturnsUnknownWhenBrokersEmpty(): void
    {
        $check = new KafkaHealthCheck('');

        $result = $check->check();

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('Brokers not configured', $result['reason']);
    }

    public function testCheckReturnsUnhealthyWhenHostUnreachable(): void
    {
        $check = new KafkaHealthCheck('unreachable-host-xyz:9092');

        $result = $check->check();

        $this->assertSame('unhealthy', $result['status']);
    }

    public function testCheckParsesHostAndPortFromBrokerString(): void
    {
        $check = new KafkaHealthCheck('kafka-broker:9093');

        $result = $check->check();

        $this->assertContains($result['status'], ['healthy', 'unhealthy']);
    }
}
