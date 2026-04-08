<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

trait RequestParseTrait
{
    protected static function parseJsonBody(Request $request): array
    {
        return json_decode($request->getContent(), true) ?? [];
    }
}
