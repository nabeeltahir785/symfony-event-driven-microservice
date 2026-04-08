<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ExceptionSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriberTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private ExceptionSubscriber $subscriber;
    private HttpKernelInterface&MockObject $kernel;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new ExceptionSubscriber($this->logger);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    private function createEvent(Request $request, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );
    }

    public function testGetSubscribedEventsListensToKernelException(): void
    {
        $events = ExceptionSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
    }

    public function testIgnoresNonApiPaths(): void
    {
        $request = Request::create('/non-api-path');
        $event = $this->createEvent($request, new \RuntimeException('test'));

        $this->subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testHandlesApiPathAndSetsJsonResponse(): void
    {
        $request = Request::create('/api/users');
        $exception = new NotFoundHttpException('User not found.');
        $event = $this->createEvent($request, $exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testReturns500ForGenericExceptions(): void
    {
        $request = Request::create('/api/users');
        $exception = new \RuntimeException('Something broke');
        $event = $this->createEvent($request, $exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('An internal server error occurred.', $body['error']['message']);
    }

    public function testReturns409ForConflictException(): void
    {
        $request = Request::create('/api/users');
        $exception = new ConflictHttpException('Email already exists.');
        $event = $this->createEvent($request, $exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(409, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('Email already exists.', $body['error']['message']);
    }

    public function testReturns422WithValidationDetails(): void
    {
        $request = Request::create('/api/users');
        $validationPayload = json_encode([
            'validation_errors' => [
                'email' => ['Email is required.'],
            ],
        ]);
        $exception = new UnprocessableEntityHttpException($validationPayload);
        $event = $this->createEvent($request, $exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('Validation failed.', $body['error']['message']);
        $this->assertArrayHasKey('details', $body['error']);
        $this->assertArrayHasKey('email', $body['error']['details']);
    }

    public function testReturns422WithoutDetailsForNonJsonMessage(): void
    {
        $request = Request::create('/api/users');
        $exception = new UnprocessableEntityHttpException('No fields provided for update.');
        $event = $this->createEvent($request, $exception);

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('No fields provided for update.', $body['error']['message']);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    public function testLogsApiException(): void
    {
        $request = Request::create('/api/users/123', 'DELETE');
        $exception = new NotFoundHttpException('Not found.');
        $event = $this->createEvent($request, $exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('API exception', $this->callback(function (array $context) {
                return $context['code'] === 404
                    && $context['path'] === '/api/users/123'
                    && $context['method'] === 'DELETE';
            }));

        $this->subscriber->onKernelException($event);
    }

    public function testResponseContentTypeIsJson(): void
    {
        $request = Request::create('/api/users');
        $exception = new NotFoundHttpException('test');
        $event = $this->createEvent($request, $exception);

        $this->subscriber->onKernelException($event);

        $this->assertSame('application/json', $event->getResponse()->headers->get('Content-Type'));
    }

    public function testResponseBodyContainsErrorStructure(): void
    {
        $request = Request::create('/api/users');
        $exception = new NotFoundHttpException('User not found.');
        $event = $this->createEvent($request, $exception);

        $this->subscriber->onKernelException($event);

        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertArrayHasKey('code', $body['error']);
        $this->assertArrayHasKey('message', $body['error']);
    }
}
