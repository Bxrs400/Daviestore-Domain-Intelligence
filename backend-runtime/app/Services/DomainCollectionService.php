<?php

namespace App\Services;

use App\Jobs\DiscoverDomainsJob;
use App\Models\CollectionJob;

class DomainCollectionService
{
    public function start(array $attributes): CollectionJob
    {
        $job = CollectionJob::create([
            'name' => $attributes['name'],
            'source' => $attributes['source'] ?? 'open-web',
            'status' => 'queued',
            'progress' => 0,
            'domains_found' => 0,
            'discovered_count' => 0,
            'validated_count' => 0,
            'active_count' => 0,
            'failed_count' => 0,
            'options' => $attributes['options'] ?? [],
        ]);

        DiscoverDomainsJob::dispatch($job->id);
        return $job;
    }
}
