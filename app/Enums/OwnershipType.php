<?php

namespace App\Enums;

enum OwnershipType: string
{
    case Independent = 'independent';
    case FamilyOwned = 'family_owned';
    case Chain = 'chain';
    case Unknown = 'unknown';
}
