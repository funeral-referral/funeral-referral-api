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

    public function test_records_of_one_source_sharing_a_home_do_not_overwrite_each_other(): void
    {
        $runner = app(CrawlRunner::class);
        $older = $this->record('older', 'Alder Funeral Home', 'Dover', 'Active');
        $newer = new SourceRecordData('newer', 'Alder Funeral Homes LLC', 'DE', ['city' => 'Lewes', 'license_number' => 'older', 'license_status' => 'Expired'], ['row' => 2]);

        $first = $runner->run($this->adapter([$older, $newer]));
        $second = $runner->run($this->adapter([$older, $newer]));

        $home = FuneralHome::query()->sole();
        $this->assertSame(1, $first->records_new);
        $this->assertSame('Dover', $home->city);
        $this->assertSame('Active', $home->license_status);
        $this->assertSame(0, $second->records_changed);
        $this->assertSame(0, ChangeEvent::query()->where('crawl_run_id', $second->id)->count());

        $changed = $this->record('older', 'Alder Funeral Home', 'Dover Heights', 'Active');
        $third = $runner->run($this->adapter([$changed, $newer]));

        $this->assertSame('Dover Heights', FuneralHome::query()->sole()->city);
        $this->assertSame(1, $third->records_changed);
    }

    public function test_a_changed_owner_is_recorded_as_an_ownership_change(): void
    {
        $runner = app(CrawlRunner::class);

        $first = new SourceRecordData('K2-3', 'Cedar Funeral Home', 'DE', ['owner_name' => 'Old Owners LLC'], ['version' => 1]);
        $second = new SourceRecordData('K2-3', 'Cedar Funeral Home', 'DE', ['owner_name' => 'New Owners LLC'], ['version' => 2]);

        $firstRun = $runner->run($this->adapter([$first]));
        $secondRun = $runner->run($this->adapter([$second]));

        $this->assertSame(0, ChangeEvent::query()->where('crawl_run_id', $firstRun->id)->where('type', 'owner_changed')->count());

        $event = ChangeEvent::query()->where('crawl_run_id', $secondRun->id)->sole();
        $this->assertSame('owner_changed', $event->type->value);
        $this->assertSame('Old Owners LLC', $event->old_value);
        $this->assertSame('New Owners LLC', $event->new_value);
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
