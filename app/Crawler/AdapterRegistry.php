<?php

namespace App\Crawler;

use App\Crawler\Contracts\SourceAdapter;

class AdapterRegistry
{
    /**
     * @return array<string, SourceAdapter>
     */
    public function all(): array
    {
        $adapters = [];

        foreach (config('crawler.adapters') as $class) {
            $adapter = app($class);
            $adapters[$adapter->key()] = $adapter;
        }

        return $adapters;
    }

    public function find(string $key): ?SourceAdapter
    {
        return $this->all()[$key] ?? null;
    }
}
