<?php

declare(strict_types=1);

namespace App\HealthCheck;

class KafkaHealthCheck extends AbstractTcpHealthCheck
{
    public function __construct(
        private readonly string $brokers,
    ) {
    }

    public function getName(): string
    {
        return 'kafka';
    }

    public function check(): array
    {
        if (empty($this->brokers)) {
            return ['status' => 'unknown', 'reason' => 'Brokers not configured'];
        }

        $parts = explode(':', $this->brokers);
        $host = $parts[0];
        $port = (int) ($parts[1] ?? 9092);

        return $this->probeConnection($host, $port);
    }
}
