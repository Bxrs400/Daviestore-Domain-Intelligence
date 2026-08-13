<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table): void { $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->timestamps(); });
        Schema::create('domains', function (Blueprint $table): void {
            $table->id(); $table->string('domain')->unique(); $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete(); $table->string('category')->nullable()->index(); $table->string('source')->nullable()->index(); $table->char('country_code', 2)->nullable(); $table->string('status')->default('pending')->index(); $table->unsignedSmallInteger('response_code')->nullable(); $table->unsignedInteger('response_time')->nullable(); $table->unsignedTinyInteger('quality_score')->default(0); $table->timestamp('first_seen_at')->nullable()->index(); $table->timestamp('last_seen_at')->nullable(); $table->timestamp('last_checked')->nullable()->index(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('domains'); Schema::dropIfExists('industries'); }
};
