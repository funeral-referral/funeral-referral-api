<?php

namespace App\Enums;

enum OutreachVariant: string
{
    case Independent = 'independent';
    case Chain = 'chain';

    public static function forOwnership(OwnershipType $type): self
    {
        return $type === OwnershipType::Chain ? self::Chain : self::Independent;
    }
}
