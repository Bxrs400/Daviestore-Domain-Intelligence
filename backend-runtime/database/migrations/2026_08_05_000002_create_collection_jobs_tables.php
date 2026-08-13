<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collection_jobs', function (Blueprint $table): void { $table->id(); $table->string('name'); $table->string('source')->default('open-web'); $table->string('status')->default('queued')->index(); $table->unsignedTinyInteger('progress')->default(0); $table->unsignedInteger('domains_found')->default(0); $table->unsignedInteger('discovered_count')->default(0); $table->unsignedInteger('validated_count')->default(0); $table->unsignedInteger('active_count')->default(0); $table->unsignedInteger('failed_count')->default(0); $table->json('options')->nullable(); $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps(); });
        Schema::create('collection_logs', function (Blueprint $table): void { $table->id(); $table->foreignId('collection_job_id')->constrained()->cascadeOnDelete(); $table->string('level', 10)->index(); $table->text('message'); $table->json('context')->nullable(); $table->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('collection_logs'); Schema::dropIfExists('collection_jobs'); }
};
