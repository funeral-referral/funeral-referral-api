<?php

namespace Tests\Feature;

use App\Crawler\Adapters\DelawareAdapter;
use App\Crawler\Support\PoliteHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DelawareAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['crawler.delay_ms' => 0]);
        PoliteHttp::forgetState();
    }

    public function test_an_active_row_beats_expired_history_rows_for_the_same_licence(): void
    {
        $this->fakeRows([
            $this->row('K2-0000457', 'Parsell Funeral Home - Clarksville', 'Lewes', 'Expired', '1998-08-31'),
            $this->row('K2-0000457', 'Parsell Funeral Homes & Crematorium', 'OCEAN VIEW', 'Active', '2028-08-31'),
            $this->row('K2-0000457', 'Parsell Funeral Home - Clarksville', 'Lewes', 'Expired', '1998-08-31'),
        ]);

        $records = iterator_to_array(app(DelawareAdapter::class)->fetch(), false);

        $this->assertCount(1, $records);
        $this->assertSame('Parsell Funeral Homes & Crematorium', $records[0]->name);
        $this->assertSame('Ocean View', $records[0]->fields['city']);
        $this->assertSame('Active', $records[0]->fields['license_status']);
    }

    public function test_without_an_active_row_the_latest_expiry_wins(): void
    {
        $this->fakeRows([
            $this->row('K2-1', 'Old Name Funeral Home', 'Dover', 'Expired', '1999-01-01'),
            $this->row('K2-1', 'Newer Name Funeral Home', 'Dover', 'Expired', '2005-01-01'),
        ]);

        $records = iterator_to_array(app(DelawareAdapter::class)->fetch(), false);

        $this->assertSame('Newer Name Funeral Home', $records[0]->name);
    }

    public function test_distinct_licences_are_all_kept(): void
    {
        $this->fakeRows([
            $this->row('K2-1', 'Alder Funeral Home', 'Dover', 'Active', '2028-01-01'),
            $this->row('K2-2', 'Birch Funeral Home', 'Lewes', 'Active', '2028-01-01'),
        ]);

        $this->assertCount(2, iterator_to_array(app(DelawareAdapter::class)->fetch(), false));
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function fakeRows(array $rows): void
    {
        Http::fake([
            'data.delaware.gov/robots.txt' => Http::response('', 404),
            'data.delaware.gov/resource/*' => Http::response($rows),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function row(string $number, string $name, string $city, string $status, string $expires): array
    {
        return [
            'combined_name' => $name,
            'license_no' => $number,
            'city' => $city,
            'state' => 'DE',
            'zip_code' => '19970',
            'license_status' => $status,
            'expiration_date' => $expires.'T00:00:00.000',
        ];
    }
}
