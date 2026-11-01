<?php

namespace App\Crawler\Adapters;

use App\Crawler\Contracts\SourceAdapter;
use App\Crawler\Data\SourceRecordData;
use App\Crawler\Support\PoliteHttp;
use RuntimeException;

abstract class ArcGisAdapter implements SourceAdapter
{
    private const PAGE_SIZE = 1000;

    /** @var array<string, true> */
    private array $issuedIds = [];

    public function __construct(protected PoliteHttp $http) {}

    /**
     * The layer's REST URL, ending in /FeatureServer/0.
     */
    abstract protected function layerUrl(): string;

    /**
     * Map one feature to the common shape, or null to skip it.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{x?: float, y?: float}|null  $geometry  Longitude (x) and latitude (y) in WGS84.
     */
    abstract protected function map(array $attributes, ?array $geometry): ?SourceRecordData;

    public function type(): string
    {
        return 'state_board';
    }

    public function fetch(): iterable
    {
        $this->issuedIds = [];
        $offset = 0;

        do {
            $response = $this->http->get($this->layerUrl().'/query', [
                'where' => '1=1',
                'outFields' => '*',
                'outSR' => 4326,
                'returnGeometry' => 'true',
                'orderByFields' => 'OBJECTID',
                'resultOffset' => $offset,
                'resultRecordCount' => self::PAGE_SIZE,
                'f' => 'json',
            ])->json();

            if (isset($response['error'])) {
                throw new RuntimeException('ArcGIS error: '.json_encode($response['error']));
            }

            $features = $response['features'] ?? [];

            foreach ($features as $feature) {
                $record = $this->map($feature['attributes'] ?? [], $feature['geometry'] ?? null);

                if ($record !== null) {
                    yield $record;
                }
            }

            $offset += count($features);
        } while (($response['exceededTransferLimit'] ?? false) === true && $features !== []);
    }

    /**
     * ArcGIS object ids are renumbered when a layer is republished, so identify a
     * feature by where it is. Two features at one address get a name suffix.
     *
     * @param  list<string>  $parts
     */
    protected function externalId(string $prefix, array $parts, string $name): string
    {
        $id = $prefix.'-'.substr(sha1(strtolower(implode('|', $parts))), 0, 16);

        if (isset($this->issuedIds[$id])) {
            $id .= '-'.substr(sha1(strtolower($name)), 0, 6);
        }

        $this->issuedIds[$id] = true;

        return $id;
    }
}
