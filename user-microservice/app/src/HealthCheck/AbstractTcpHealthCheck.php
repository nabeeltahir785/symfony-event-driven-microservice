<?php

declare(strict_types=1);

namespace App\HealthCheck;

use App\Contract\HealthCheckInterface;

abstract class AbstractTcpHealthCheck implements HealthCheckInterface
{
    protected function probeConnection(string $host, int $port, int $timeoutSeconds = 3): array
    {
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);

            if ($connection) {
                fclose($connection);
                return ['status' => 'healthy', 'host' => $host, 'port' => $port];
            }

            return ['status' => 'unhealthy', 'error' => $errstr];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }
}
