<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_homes', function (Blueprint $table): void {
            $table->string('county_fips', 5)->nullable()->index()->after('county');
        });
    }

    public function down(): void
    {
        Schema::table('funeral_homes', function (Blueprint $table): void {
            $table->dropColumn('county_fips');
        });
    }
};
