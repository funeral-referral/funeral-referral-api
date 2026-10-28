<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funeral_home_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('variant');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('stopped_reason')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedTinyInteger('position');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('edited')->default(false);
            $table->string('status')->default('draft')->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funeral_home_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_email')->index();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason');
            $table->foreignId('funeral_home_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('email_replies');
        Schema::dropIfExists('campaign_messages');
        Schema::dropIfExists('campaigns');
    }
};
