<?php

namespace App\Jobs;

use App\Models\CollectionJob;
use App\Models\Domain;
use App\Services\DomainValidationService;
use App\Services\LaravelTechnologyDetectionService;
use App\Services\WebsiteCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateDomainsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $collectionJobId, public array $domains) {}
    public function handle(DomainValidationService $validator, WebsiteCrawlerService $crawler, LaravelTechnologyDetectionService $detector): void
    {
        $job = CollectionJob::findOrFail($this->collectionJobId); $validated = 0;
        foreach ($this->domains as $domainName) {
            if ($job->fresh()->status === 'paused') { self::dispatch($job->id, array_slice($this->domains, $validated)); return; }
            $result = $validator->validate($domainName); $domain = Domain::where('domain', $domainName)->first();
            $domain?->update(['status' => $result['status'], 'response_code' => $result['response_code'], 'response_time' => $result['response_time'], 'last_checked' => now()]);
            if ($domain && $result['status'] === 'verified') {
                $technology = $detector->detect("https://{$domainName}", $crawler->crawl("https://{$domainName}"));
                $domain->update(['technology' => $technology, 'laravel_confidence' => $technology['laravel_confidence'], 'laravel_confidence_label' => $technology['laravel_confidence_label'], 'technology_checked_at' => $technology['checked_at']]);
                $job->logs()->create(['level' => 'INFO', 'message' => "Detected technology for {$domainName}", 'context' => $technology]);
            }
            $validated++; $job->update(['validated_count' => $validated, 'progress' => min(75, 35 + (int) (($validated / max(1, count($this->domains))) * 40))]);
            $job->logs()->create(['level' => $result['status'] === 'verified' ? 'PASS' : 'WARN', 'message' => "Validated {$domainName}", 'context' => $result]);
        }
        $job->update(['validated_count' => $validated]);
        $options = $job->options ?? [];
        $strict = (bool) ($options['strict_technology_filter'] ?? false);
        $candidates = array_values(array_filter($this->domains, fn (string $domain) => Domain::where('domain', $domain)->where('status', 'verified')->exists()));
        if ($strict && in_array('laravel', $options['technology'] ?? [], true)) {
            $minimum = (int) ($options['minimum_technology_confidence'] ?? 70);
            $candidates = array_values(array_filter($this->domains, function (string $domain) use ($minimum): bool {
                $record = Domain::where('domain', $domain)->where('status', 'verified')->first();
                return $record && (bool) ($record->technology['laravel_detected'] ?? false) && (int) ($record->laravel_confidence ?? 0) >= $minimum;
            }));
        }
        ClassifyDomainsJob::dispatch($job->id, $candidates);
    }
}
