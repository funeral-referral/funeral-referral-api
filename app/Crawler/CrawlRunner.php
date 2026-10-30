<?php

namespace App\Crawler;

use App\Crawler\Contracts\SourceAdapter;
use App\Crawler\Data\SourceRecordData;
use App\Crawler\Matching\Matcher;
use App\Crawler\Support\Normalizer;
use App\Enums\ChangeType;
use App\Models\ChangeEvent;
use App\Models\CrawlRun;
use App\Models\FieldValue;
use App\Models\FuneralHome;
use App\Models\Source;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrawlRunner
{
    private const TRACKED_FIELDS = [
        'name', 'address_line1', 'city', 'state_code', 'postal_code', 'county',
        'phone', 'email', 'website', 'latitude', 'longitude', 'license_number',
        'license_status', 'licensed_director', 'offers_preneed', 'offers_burial',
        'offers_cremation',
    ];

    private const BOOLEAN_FIELDS = ['offers_preneed', 'offers_burial', 'offers_cremation'];

    private const COORDINATE_FIELDS = ['latitude', 'longitude'];

    public function __construct(private readonly Matcher $matcher) {}

    public function run(SourceAdapter $adapter): CrawlRun
    {
        $source = Source::query()->updateOrCreate(
            ['key' => $adapter->key()],
            ['name' => $adapter->name(), 'type' => $adapter->type(), 'state_code' => $adapter->stateCode()],
        );

        $run = CrawlRun::query()->create([
            'source_id' => $source->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $counts = ['seen' => 0, 'new' => 0, 'changed' => 0, 'errors' => 0];
        $errors = [];
        $status = 'completed';
        $fatal = null;

        try {
            foreach ($adapter->fetch() as $record) {
                $counts['seen']++;

                try {
                    $outcome = DB::transaction(fn (): string => $this->ingest($source, $run, $record));
                } catch (Throwable $e) {
                    $counts['errors']++;
                    $errors[] = $record->externalId.': '.$e->getMessage();

                    continue;
                }

                if ($outcome === 'new') {
                    $counts['new']++;
                } elseif ($outcome === 'changed') {
                    $counts['changed']++;
                }
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $fatal = $e->getMessage();
            report($e);
        }

        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'records_seen' => $counts['seen'],
            'records_new' => $counts['new'],
            'records_changed' => $counts['changed'],
            'error' => $fatal,
            'stats' => ['record_errors' => $counts['errors'], 'sample_errors' => array_slice($errors, 0, 5)],
        ]);

        return $run->refresh();
    }

    /**
     * @return string One of "new", "changed" or "unchanged".
     */
    private function ingest(Source $source, CrawlRun $run, SourceRecordData $data): string
    {
        $hash = hash('sha256', (string) json_encode($data->payload, JSON_UNESCAPED_UNICODE));

        $existing = SourceRecord::query()
            ->where('source_id', $source->id)
            ->where('external_id', $data->externalId)
            ->first();

        if ($existing !== null && $existing->content_hash === $hash && $existing->funeral_home_id !== null) {
            $existing->update(['fetched_at' => now(), 'crawl_run_id' => $run->id]);
            FuneralHome::query()->whereKey($existing->funeral_home_id)->update(['last_seen_at' => now()]);

            return 'unchanged';
        }

        $fields = $this->trackedFields($data);
        $created = false;
        $score = 1.0;

        if ($existing?->funeral_home_id !== null) {
            $home = FuneralHome::query()->findOrFail($existing->funeral_home_id);
        } elseif (($match = $this->matcher->find($data)) !== null) {
            [$home, $score] = $match;
        } else {
            $home = FuneralHome::query()->create([
                'name' => $data->name,
                'normalized_name' => Normalizer::name($data->name),
                'state_code' => $data->stateCode,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
            $created = true;
        }

        $record = $existing ?? new SourceRecord(['source_id' => $source->id, 'external_id' => $data->externalId]);
        $record->fill([
            'crawl_run_id' => $run->id,
            'funeral_home_id' => $home->id,
            'payload' => $data->payload,
            'normalized' => $fields,
            'content_hash' => $hash,
            'match_score' => $score,
            'fetched_at' => now(),
        ])->save();

        if ($created) {
            $this->recordEvent($home, ChangeType::NewHome, $source, $run);
        }

        $changed = $this->merge($home, $source, $record, $run, $fields, emitEvents: $existing !== null);

        if ($created) {
            return 'new';
        }

        return $changed !== [] ? 'changed' : 'unchanged';
    }

    /**
     * @return array<string, mixed>
     */
    private function trackedFields(SourceRecordData $data): array
    {
        $all = ['name' => $data->name, 'state_code' => $data->stateCode] + $data->fields;
        $tracked = [];

        foreach (self::TRACKED_FIELDS as $field) {
            if (isset($all[$field]) && $all[$field] !== '') {
                $tracked[$field] = $all[$field];
            }
        }

        return $tracked;
    }

    /**
     * Record this source's observations, then recompute the home's canonical values.
     *
     * @param  array<string, mixed>  $fields
     * @return list<string> The fields whose canonical value changed.
     */
    private function merge(FuneralHome $home, Source $source, SourceRecord $record, CrawlRun $run, array $fields, bool $emitEvents): array
    {
        $observed = [];

        foreach ($fields as $field => $value) {
            $stored = $this->stringify($field, $value);

            $current = FieldValue::query()
                ->where('funeral_home_id', $home->id)
                ->where('field', $field)
                ->where('source_id', $source->id)
                ->where('is_current', true)
                ->first();

            if ($current?->value === $stored) {
                continue;
            }

            $current?->update(['is_current' => false]);

            FieldValue::query()->create([
                'funeral_home_id' => $home->id,
                'field' => $field,
                'value' => $stored,
                'source_id' => $source->id,
                'source_record_id' => $record->id,
                'observed_at' => now(),
                'is_current' => true,
            ]);

            $observed[] = $field;
        }

        $updates = ['last_seen_at' => now()];
        $changed = [];

        foreach ($observed as $field) {
            $old = $this->stringify($field, $home->{$field});
            $new = $this->canonicalValue($home, $field);

            if ($new === null || $new === $old) {
                continue;
            }

            $changed[] = $field;
            $updates[$field] = $this->castForColumn($field, $new);

            if ($field === 'name') {
                $updates['normalized_name'] = Normalizer::name($new);
            }

            if ($field === 'website') {
                $updates['domain'] = Normalizer::domain($new);
            }

            if ($field === 'license_status') {
                $updates['closed_at'] = $this->isClosedStatus($new) ? ($home->closed_at ?? now()) : null;
            }

            if ($emitEvents) {
                $this->emitFieldEvents($home, $field, $old, $new, $source, $run);
            }
        }

        $home->forceFill($updates)->save();

        return $changed;
    }

    private function canonicalValue(FuneralHome $home, string $field): ?string
    {
        $priority = config('crawler.source_priority');

        $rows = FieldValue::query()
            ->join('sources', 'sources.id', '=', 'field_values.source_id')
            ->where('field_values.funeral_home_id', $home->id)
            ->where('field_values.field', $field)
            ->where('field_values.is_current', true)
            ->get(['field_values.value', 'field_values.observed_at', 'sources.type']);

        $best = $rows
            ->sortByDesc(fn ($row): string => sprintf('%d-%s', $priority[$row->type] ?? 0, $row->observed_at))
            ->first();

        return $best?->value;
    }

    private function emitFieldEvents(FuneralHome $home, string $field, ?string $old, string $new, Source $source, CrawlRun $run): void
    {
        $types = match ($field) {
            'license_number' => [ChangeType::NewLicense],
            'license_status' => $this->isClosedStatus($new)
                ? [ChangeType::LicenseStatusChanged, ChangeType::Closure]
                : [ChangeType::LicenseStatusChanged],
            'name' => [ChangeType::NameChanged],
            'website' => [$old === null ? ChangeType::NewWebsite : ChangeType::WebsiteChanged],
            'phone' => [$old === null ? ChangeType::NewPhone : ChangeType::PhoneChanged],
            'email' => [ChangeType::EmailChanged],
            'address_line1', 'city', 'postal_code' => [ChangeType::AddressChanged],
            default => [],
        };

        foreach ($types as $type) {
            $this->recordEvent($home, $type, $source, $run, $field, $old, $new);
        }
    }

    private function recordEvent(FuneralHome $home, ChangeType $type, Source $source, CrawlRun $run, ?string $field = null, ?string $old = null, ?string $new = null): void
    {
        ChangeEvent::query()->create([
            'funeral_home_id' => $home->id,
            'type' => $type,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'source_id' => $source->id,
            'crawl_run_id' => $run->id,
            'occurred_at' => now(),
        ]);
    }

    private function isClosedStatus(string $status): bool
    {
        $status = strtolower($status);

        foreach (config('crawler.closed_status_keywords') as $keyword) {
            if (str_contains($status, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function stringify(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return $value ? '1' : '0';
        }

        if (in_array($field, self::COORDINATE_FIELDS, true)) {
            return sprintf('%.7f', (float) $value);
        }

        return (string) $value;
    }

    private function castForColumn(string $field, string $value): mixed
    {
        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return $value === '1';
        }

        if (in_array($field, self::COORDINATE_FIELDS, true)) {
            return (float) $value;
        }

        return $value;
    }
}
