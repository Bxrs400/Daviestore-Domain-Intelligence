<?php

namespace App\Jobs;

use App\Models\CollectionJob;
use App\Models\Domain;
use App\Services\DomainDiscoveryService;
use App\Services\WebsiteCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DiscoverDomainsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $collectionJobId) {}
    public function handle(DomainDiscoveryService $discovery, WebsiteCrawlerService $crawler): void
    {
        $job = CollectionJob::findOrFail($this->collectionJobId);
        $job->update(['status' => 'running', 'started_at' => $job->started_at ?: now()]);
        $result = $discovery->discover($job->options ?? []);
        $job->logs()->create(['level' => 'INFO', 'message' => 'Generated discovery queries', 'context' => ['queries' => $result['queries'], 'providers' => $result['provider_stats']]]);
        $domains = collect($result['domains']);
        foreach ($result['urls'] as $url) $domains = $domains->merge(collect($crawler->crawl($url)['links'])->map(fn (string $link) => $crawler->rootDomain($link)));
        $domains = $domains->filter()->unique()->values();
        foreach ($domains as $domain) Domain::firstOrCreate(['domain' => $domain], ['source' => $job->source, 'status' => 'pending', 'first_seen_at' => now()]);
        $job->update(['discovered_count' => $domains->count(), 'domains_found' => $domains->count(), 'progress' => 35]);
        $job->logs()->create(['level' => 'PASS', 'message' => "Discovered {$domains->count()} unique root domains", 'context' => ['provider_stats' => $result['provider_stats'], 'candidate_count' => count($result['candidates'])]]);
        ValidateDomainsJob::dispatch($job->id, $domains->all());
    }
}
