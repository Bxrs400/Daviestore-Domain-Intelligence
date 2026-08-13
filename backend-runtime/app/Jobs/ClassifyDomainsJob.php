<?php

namespace App\Jobs;

use App\Models\CollectionJob;
use App\Models\Domain;
use App\Services\IndustryClassificationService;
use App\Services\WebsiteCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyDomainsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $collectionJobId, public array $domains) {}
    public function handle(WebsiteCrawlerService $crawler, IndustryClassificationService $classifier): void
    {
        $job = CollectionJob::findOrFail($this->collectionJobId);
        foreach ($this->domains as $index => $domainName) { $page = $crawler->crawl("https://{$domainName}"); $classification = $classifier->classify($page); Domain::where('domain', $domainName)->update(['category' => $classification['category'], 'quality_score' => $classification['confidence'], 'last_seen_at' => now()]); $job->update(['active_count' => $index + 1, 'progress' => min(99, 75 + (int) ((($index + 1) / max(1, count($this->domains))) * 24))]); }
        $job->update(['status' => 'completed', 'progress' => 100, 'active_count' => count($this->domains), 'completed_at' => now()]);
        $job->logs()->create(['level' => 'PASS', 'message' => 'Collection completed', 'context' => ['classified' => count($this->domains)]]);
    }
}
