<?php

namespace App\Outreach;

use App\Enums\OutreachVariant;
use App\Models\FuneralHome;

interface MessageWriter
{
    /**
     * Draft the whole sequence for one home.
     *
     * @return array<string, array{subject?: string, body: string}> keyed by MessageType value
     */
    public function write(FuneralHome $home, OutreachVariant $variant): array;
}
