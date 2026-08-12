<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionJob extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'source', 'status', 'progress', 'domains_found', 'discovered_count', 'validated_count', 'active_count', 'failed_count', 'started_at', 'completed_at', 'options'];
    public const STATUSES = ['queued', 'running', 'paused', 'completed', 'failed'];
    protected $casts = ['progress' => 'integer', 'domains_found' => 'integer', 'discovered_count' => 'integer', 'validated_count' => 'integer', 'active_count' => 'integer', 'failed_count' => 'integer', 'options' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    public function logs(): HasMany { return $this->hasMany(CollectionLog::class); }
}
