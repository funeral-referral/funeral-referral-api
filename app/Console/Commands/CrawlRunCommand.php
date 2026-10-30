<?php

namespace App\Console\Commands;

use App\Crawler\AdapterRegistry;
use App\Crawler\CrawlRunner;
use Illuminate\Console\Command;

class CrawlRunCommand extends Command
{
    protected $signature = 'crawl:run {source? : Run only this source key}';

    protected $description = 'Crawl funeral home sources and record what changed';

    public function handle(AdapterRegistry $registry, CrawlRunner $runner): int
    {
        $adapters = $registry->all();

        if ($this->argument('source') !== null) {
            $adapter = $registry->find($this->argument('source'));

            if ($adapter === null) {
                $this->error('Unknown source. Available: '.implode(', ', array_keys($adapters)));

                return self::INVALID;
            }

            $adapters = [$adapter->key() => $adapter];
        }

        $rows = [];
        $failed = false;

        foreach ($adapters as $adapter) {
            $this->info('Crawling '.$adapter->name().' ...');

            $run = $runner->run($adapter);
            $failed = $failed || $run->status === 'failed';

            $rows[] = [$adapter->key(), $run->status, $run->records_seen, $run->records_new, $run->records_changed, $run->stats['record_errors'] ?? 0];
        }

        $this->table(['Source', 'Status', 'Seen', 'New', 'Changed', 'Errors'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
