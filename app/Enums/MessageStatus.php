<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
