<?php

namespace App\Crawler\Contracts;

use App\Crawler\Data\SourceRecordData;

interface SourceAdapter
{
    /**
     * Stable identifier stored on the source row, e.g. "co-dora-funeral-homes".
     */
    public function key(): string;

    public function name(): string;

    /**
     * Source type, used to rank sources: state_board, osm or website.
     */
    public function type(): string;

    public function stateCode(): ?string;

    /**
     * Stream the source's records, already mapped to the common shape.
     *
     * @return iterable<SourceRecordData>
     */
    public function fetch(): iterable;
}
