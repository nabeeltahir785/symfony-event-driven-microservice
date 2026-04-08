<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

class CorrelationIdListener implements EventSubscriberInterface
{
    public const HEADER = 'X-Request-ID';
    public const ATTRIBUTE = '_correlation_id';

    private ?string $correlationId = null;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
            KernelEvents::RESPONSE => ['onKernelResponse', -255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->correlationId = $request->headers->get(self::HEADER, Uuid::v4()->toRfc4122());
        $request->attributes->set(self::ATTRIBUTE, $this->correlationId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->correlationId === null) {
            return;
        }

        $event->getResponse()->headers->set(self::HEADER, $this->correlationId);
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }
}
