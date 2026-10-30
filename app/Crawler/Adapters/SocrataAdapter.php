<?php

namespace App\Crawler\Adapters;

use App\Crawler\Contracts\SourceAdapter;
use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\PoliteHttp;

abstract class SocrataAdapter implements SourceAdapter
{
    private const PAGE_SIZE = 1000;

    public function __construct(protected PoliteHttp $http) {}

    abstract protected function endpoint(): string;

    /**
     * Extra SoQL parameters, such as a $where filter.
     *
     * @return array<string, string>
     */
    abstract protected function query(): array;

    /**
     * Map one API row to the common shape, or null to skip it.
     *
     * @param  array<string, mixed>  $row
     */
    abstract protected function map(array $row): ?SourceRecordData;

    public function type(): string
    {
        return 'state_board';
    }

    public function fetch(): iterable
    {
        $offset = 0;

        do {
            $rows = $this->http->get($this->endpoint(), $this->query() + [
                '$limit' => self::PAGE_SIZE,
                '$offset' => $offset,
                '$order' => ':id',
            ])->json();

            foreach ($rows as $row) {
                $record = $this->map($row);

                if ($record !== null) {
                    yield $record;
                }
            }

            $offset += self::PAGE_SIZE;
        } while (count($rows) === self::PAGE_SIZE);
    }
}
