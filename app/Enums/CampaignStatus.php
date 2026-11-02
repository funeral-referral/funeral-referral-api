<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Stopped = 'stopped';
    case Completed = 'completed';
}
