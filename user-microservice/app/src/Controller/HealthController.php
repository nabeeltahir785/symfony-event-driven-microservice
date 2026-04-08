<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\HealthCheckInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    private iterable $healthChecks;

    public function __construct(iterable $healthChecks)
    {
        $this->healthChecks = $healthChecks;
    }

    #[OA\Get(
        path: '/api/health',
        summary: 'Service health check',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'All services healthy'),
            new OA\Response(response: 503, description: 'One or more services degraded'),
        ]
    )]
    #[Route('/api/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $checks = [];

        foreach ($this->healthChecks as $healthCheck) {
            $checks[$healthCheck->getName()] = $healthCheck->check();
        }

        $allHealthy = !in_array('unhealthy', array_column($checks, 'status'));

        return $this->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'service' => 'user-microservice',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }
}
