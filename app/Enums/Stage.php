<?php

namespace App\Enums;

enum Stage: string
{
    case Found = 'found';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Meeting = 'meeting';
    case ActivePartner = 'active_partner';
    case NotInterested = 'not_interested';
}
