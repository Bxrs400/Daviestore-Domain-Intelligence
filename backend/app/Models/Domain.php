<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = ['domain', 'industry_id', 'category', 'source', 'country_code', 'status', 'response_code', 'response_time', 'quality_score', 'technology', 'laravel_confidence', 'laravel_confidence_label', 'first_seen_at', 'last_seen_at', 'last_checked', 'technology_checked_at'];
    public const STATUSES = ['pending', 'verified', 'rejected', 'failed'];
    protected $casts = ['quality_score' => 'integer', 'response_code' => 'integer', 'response_time' => 'integer', 'laravel_confidence' => 'integer', 'technology' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'last_checked' => 'datetime', 'technology_checked_at' => 'datetime'];

    public function industry(): BelongsTo { return $this->belongsTo(Industry::class); }
}
