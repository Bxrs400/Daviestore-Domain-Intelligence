<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DiscoverDomainsJob;
use App\Models\CollectionJob;
use App\Services\DomainCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CollectionJobController extends Controller
{
    public function store(Request $request, DomainCollectionService $service): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'source' => ['required', 'string', 'max:80'],
            'options' => ['required', 'array'],
            'options.keywords' => ['sometimes', 'array'],
            'options.keywords.*' => ['string', 'max:120'],
            'options.categories' => ['sometimes', 'array'],
            'options.categories.*' => ['string', 'max:120'],
            'options.seed_urls' => ['sometimes', 'array'],
            'options.seed_urls.*' => ['url', 'max:2048'],
            'options.max_domains' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'options.technology' => ['sometimes', 'array'],
            'options.technology.*' => ['string', 'in:laravel'],
            'options.minimum_technology_confidence' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'options.strict_technology_filter' => ['sometimes', 'boolean'],
            'options.providers' => ['sometimes', 'array'],
            'options.providers.*' => ['string','in:local_seed,google,wikidata,github,aws_agentcore_web_search',],
            'options.max_results' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'options.max_queries' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'options.include_domains' => ['sometimes', 'array'],
            'options.include_domains.*' => ['string', 'max:255'],
            'options.exclude_domains' => ['sometimes', 'array'],
            'options.exclude_domains.*' => ['string', 'max:255'],
            'options.language' => ['sometimes', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'The collection job payload is invalid.', $validator->errors()->toArray(), 422);
        }

        $payload = $validator->validated();
        $payload['options']['strict_technology_filter'] = (bool) ($payload['options']['strict_technology_filter'] ?? false);
        $job = $service->start($payload);
        return response()->json(['success' => true, 'data' => $this->serializeJob($job)], 202);
    }

    public function show(CollectionJob $job): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->serializeJob($job->loadCount('logs'))]);
    }

    public function logs(Request $request, CollectionJob $job): JsonResponse
    {
        $logs = $job->logs()->latest()->paginate($this->perPage($request));
        return response()->json(['success' => true, 'data' => $this->pagination($logs, fn ($log) => [
            'id' => $log->id,
            'level' => $log->level,
            'message' => $log->message,
            'context' => $log->context,
            'created_at' => optional($log->created_at)->toISOString(),
        ])]);
    }

    public function pause(CollectionJob $job): JsonResponse
    {
        if (!in_array($job->status, ['queued', 'running'], true)) {
            return $this->error('INVALID_STATUS_TRANSITION', 'Only queued or running jobs can be paused.', null, 409);
        }
        $job->update(['status' => 'paused']);
        $job->logs()->create(['level' => 'INFO', 'message' => 'Collection paused by operator']);
        return response()->json(['success' => true, 'data' => $this->serializeJob($job->fresh())]);
    }

    public function resume(CollectionJob $job): JsonResponse
    {
        if ($job->status !== 'paused') {
            return $this->error('INVALID_STATUS_TRANSITION', 'Only paused jobs can be resumed.', null, 409);
        }
        $job->update(['status' => 'queued']);
        $job->logs()->create(['level' => 'INFO', 'message' => 'Collection resumed by operator']);
        DiscoverDomainsJob::dispatch($job->id);
        return response()->json(['success' => true, 'data' => $this->serializeJob($job->fresh())]);
    }

    private function serializeJob(CollectionJob $job): array
    {
        return [
            'id' => (string) $job->id,
            'name' => $job->name,
            'source' => $job->source,
            'status' => $job->status,
            'progress' => (int) $job->progress,
            'domains_found' => (int) $job->domains_found,
            'discovered_count' => (int) $job->discovered_count,
            'validated_count' => (int) $job->validated_count,
            'active_count' => (int) $job->active_count,
            'failed_count' => (int) $job->failed_count,
            'options' => $job->options ?? [],
            'started_at' => optional($job->started_at)->toISOString(),
            'completed_at' => optional($job->completed_at)->toISOString(),
            'created_at' => optional($job->created_at)->toISOString(),
            'updated_at' => optional($job->updated_at)->toISOString(),
        ];
    }

    private function pagination($paginator, callable $map): array
    {
        return [
            'data' => collect($paginator->items())->map($map)->values()->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 25), 1), 100);
    }

    private function error(string $code, string $message, ?array $fields, int $status): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== null) $error['fields'] = $fields;
        return response()->json(['success' => false, 'error' => $error], $status);
    }
}
