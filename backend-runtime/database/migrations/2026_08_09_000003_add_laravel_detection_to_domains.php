<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->json('technology')->nullable()->after('quality_score');
            $table->unsignedTinyInteger('laravel_confidence')->nullable()->after('technology')->index();
            $table->string('laravel_confidence_label')->nullable()->after('laravel_confidence')->index();
            $table->timestamp('technology_checked_at')->nullable()->after('last_checked')->index();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropColumn(['technology', 'laravel_confidence', 'laravel_confidence_label', 'technology_checked_at']);
        });
    }
};
