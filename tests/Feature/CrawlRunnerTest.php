<?php

namespace Tests\Feature;

use App\Crawler\Contracts\SourceAdapter;
use App\Crawler\CrawlRunner;
use App\Crawler\Data\SourceRecordData;
use App\Models\ChangeEvent;
use App\Models\FuneralHome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_repeated_record_id_is_ignored_so_values_do_not_flip_between_crawls(): void
    {
        $adapter = $this->adapter([
            $this->record('K2-1', 'Parsell Funeral Homes', 'Ocean View', 'Active'),
            $this->record('K2-1', 'Parsell Funeral Home - Clarksville', 'Lewes', 'Expired'),
        ]);
        $runner = app(CrawlRunner::class);

        $first = $runner->run($adapter);
        $second = $runner->run($adapter);

        $this->assertSame(2, $first->records_seen);
        $this->assertSame(1, $first->records_new);
        $this->assertSame(1, $first->stats['duplicate_rows']);
        $this->assertSame(0, $second->records_new);
        $this->assertSame(0, $second->records_changed);
        $this->assertSame('Ocean View', FuneralHome::query()->sole()->city);
        $this->assertSame(0, ChangeEvent::query()->where('crawl_run_id', $second->id)->count());
    }

    public function test_a_real_change_between_crawls_is_recorded_once(): void
    {
        $runner = app(CrawlRunner::class);

        $runner->run($this->adapter([$this->record('K2-2', 'Alder Funeral Home', 'Dover', 'Active')]));
        $second = $runner->run($this->adapter([$this->record('K2-2', 'Alder Funeral Home', 'Dover', 'Revoked')]));

        $this->assertSame(1, $second->records_changed);
        $this->assertSame(
            ['license_status_changed', 'closure'],
            ChangeEvent::query()->where('crawl_run_id', $second->id)->orderBy('id')->get()->map(fn (ChangeEvent $event): string => $event->type->value)->all(),
        );
    }

    /**
     * @param  list<SourceRecordData>  $records
     */
    private function adapter(array $records): SourceAdapter
    {
        return new class($records) implements SourceAdapter
        {
            /**
             * @param  list<SourceRecordData>  $records
             */
            public function __construct(private readonly array $records) {}

            public function key(): string
            {
                return 'test-source';
            }

            public function name(): string
            {
                return 'Test source';
            }

            public function type(): string
            {
                return 'state_board';
            }

            public function stateCode(): ?string
            {
                return 'DE';
            }

            public function fetch(): iterable
            {
                yield from $this->records;
            }
        };
    }

    private function record(string $id, string $name, string $city, string $status): SourceRecordData
    {
        return new SourceRecordData(
            externalId: $id,
            name: $name,
            stateCode: 'DE',
            fields: ['city' => $city, 'license_number' => $id, 'license_status' => $status],
            payload: ['name' => $name, 'city' => $city, 'status' => $status],
        );
    }
}
