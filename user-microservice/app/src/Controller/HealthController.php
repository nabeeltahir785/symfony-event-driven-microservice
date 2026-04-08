<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $checks = [];

        $checks['database'] = $this->checkDatabase();
        $checks['rabbitmq'] = $this->checkRabbitMQ();
        $checks['kafka'] = $this->checkKafka();

        $allHealthy = !in_array('unhealthy', array_column($checks, 'status'));

        return $this->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'service' => 'user-microservice',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');
            return ['status' => 'healthy', 'driver' => 'pdo_pgsql'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkRabbitMQ(): array
    {
        try {
            $dsn = $_ENV['MESSENGER_TRANSPORT_DSN'] ?? '';
            if (empty($dsn)) {
                return ['status' => 'unknown', 'reason' => 'DSN not configured'];
            }

            $parsed = parse_url($dsn);
            $host = $parsed['host'] ?? 'rabbitmq';
            $port = $parsed['port'] ?? 5672;

            $connection = @fsockopen($host, (int) $port, $errno, $errstr, 3);
            if ($connection) {
                fclose($connection);
                return ['status' => 'healthy', 'host' => $host, 'port' => (int) $port];
            }

            return ['status' => 'unhealthy', 'error' => $errstr];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkKafka(): array
    {
        try {
            $brokers = $_ENV['KAFKA_BROKERS'] ?? '';
            if (empty($brokers)) {
                return ['status' => 'unknown', 'reason' => 'Brokers not configured'];
            }

            $parts = explode(':', $brokers);
            $host = $parts[0];
            $port = (int) ($parts[1] ?? 9092);

            $connection = @fsockopen($host, $port, $errno, $errstr, 3);
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
