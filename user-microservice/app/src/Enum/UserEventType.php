<?php

declare(strict_types=1);

namespace App\Enum;

enum UserEventType: string
{
    case Created = 'user.created';
    case Updated = 'user.updated';
    case Deleted = 'user.deleted';
}
