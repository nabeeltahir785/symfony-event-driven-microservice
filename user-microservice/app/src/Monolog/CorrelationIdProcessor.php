<?php

declare(strict_types=1);

namespace App\Monolog;

use App\EventListener\CorrelationIdListener;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class CorrelationIdProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CorrelationIdListener $correlationIdListener,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $correlationId = $this->correlationIdListener->getCorrelationId();

        if ($correlationId !== null) {
            $record->extra['correlation_id'] = $correlationId;
        }

        return $record;
    }
}
