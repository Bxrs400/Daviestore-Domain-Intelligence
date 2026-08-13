<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionLog extends Model
{
    use HasFactory;
    protected $fillable = ['collection_job_id', 'level', 'message', 'context'];
    protected $casts = ['context' => 'array'];
    public function job(): BelongsTo { return $this->belongsTo(CollectionJob::class, 'collection_job_id'); }
}
