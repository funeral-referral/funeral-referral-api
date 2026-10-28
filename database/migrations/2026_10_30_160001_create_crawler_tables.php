<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('type');
            $table->char('state_code', 2)->nullable()->index();
            $table->boolean('enabled')->default(true);
            $table->jsonb('config')->nullable();
            $table->timestamps();
        });

        Schema::create('crawl_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('running')->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_new')->default(0);
            $table->unsignedInteger('records_changed')->default(0);
            $table->text('error')->nullable();
            $table->jsonb('stats')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'started_at']);
        });

        Schema::create('funeral_homes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('address_line1')->nullable();
            $table->string('city')->nullable();
            $table->char('state_code', 2)->index();
            $table->string('county')->nullable()->index();
            $table->string('postal_code', 10)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('domain')->nullable()->index();
            $table->jsonb('social_links')->nullable();
            $table->string('license_number')->nullable()->index();
            $table->string('license_status')->nullable();
            $table->string('licensed_director')->nullable();
            $table->string('ownership_type')->default('unknown')->index();
            $table->string('chain_name')->nullable();
            $table->boolean('offers_burial')->nullable();
            $table->boolean('offers_cremation')->nullable();
            $table->boolean('offers_preneed')->nullable();
            $table->boolean('sells_insurance')->nullable();
            $table->decimal('review_rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->unsignedSmallInteger('partner_score')->nullable()->index();
            $table->jsonb('partner_score_breakdown')->nullable();
            $table->string('stage')->default('found')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX funeral_homes_normalized_name_trgm ON funeral_homes USING gin (normalized_name gin_trgm_ops)');

        Schema::create('source_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funeral_home_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->jsonb('payload');
            $table->jsonb('normalized')->nullable();
            $table->string('content_hash', 64);
            $table->decimal('match_score', 5, 4)->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
        });

        Schema::create('field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funeral_home_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->text('value')->nullable();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_record_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('observed_at');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['funeral_home_id', 'field', 'is_current']);
        });

        Schema::create('change_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funeral_home_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crawl_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_events');
        Schema::dropIfExists('field_values');
        Schema::dropIfExists('source_records');
        Schema::dropIfExists('funeral_homes');
        Schema::dropIfExists('crawl_runs');
        Schema::dropIfExists('sources');
    }
};
