<?php

declare(strict_types=1);

namespace App\Contract;

interface MessageInterface
{
    public function getUserId(): string;

    public function getOccurredAt(): string;

    public function toArray(): array;
}
