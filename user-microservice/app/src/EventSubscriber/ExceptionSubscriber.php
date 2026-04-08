<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        $responseBody = [
            'error' => [
                'code' => $statusCode,
                'message' => $this->resolveMessage($exception, $statusCode),
            ],
        ];

        if ($statusCode === 422) {
            $decoded = json_decode($exception->getMessage(), true);
            if (is_array($decoded) && isset($decoded['validation_errors'])) {
                $responseBody['error']['message'] = 'Validation failed.';
                $responseBody['error']['details'] = $decoded['validation_errors'];
            }
        }

        $this->logger->error('API exception', [
            'code' => $statusCode,
            'message' => $exception->getMessage(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
        ]);

        $response = new JsonResponse($responseBody, $statusCode);
        $response->headers->set('Content-Type', 'application/json');

        $event->setResponse($response);
    }

    private function resolveMessage(\Throwable $exception, int $statusCode): string
    {
        if ($statusCode === 500) {
            return 'An internal server error occurred.';
        }

        return $exception->getMessage();
    }
}
