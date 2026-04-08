<?php

declare(strict_types=1);

namespace App\Contract;

interface EventPublisherInterface
{
    public function publish(string $key, array $payload): void;
}
