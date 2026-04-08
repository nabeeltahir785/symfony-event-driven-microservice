<?php

declare(strict_types=1);

namespace App\Contract;

interface HealthCheckInterface
{
    public function getName(): string;

    public function check(): array;
}
