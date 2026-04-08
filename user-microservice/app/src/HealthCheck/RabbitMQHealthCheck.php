<?php

declare(strict_types=1);

namespace App\HealthCheck;

class RabbitMQHealthCheck extends AbstractTcpHealthCheck
{
    public function __construct(
        private readonly string $transportDsn,
    ) {
    }

    public function getName(): string
    {
        return 'rabbitmq';
    }

    public function check(): array
    {
        if (empty($this->transportDsn)) {
            return ['status' => 'unknown', 'reason' => 'DSN not configured'];
        }

        $parsed = parse_url($this->transportDsn);
        $host = $parsed['host'] ?? 'rabbitmq';
        $port = (int) ($parsed['port'] ?? 5672);

        return $this->probeConnection($host, $port);
    }
}
