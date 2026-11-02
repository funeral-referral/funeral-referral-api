<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('generated_by')->default('template')->after('variant');
        });

        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->string('sent_to')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->dropColumn('sent_to');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn('generated_by');
        });
    }
};
