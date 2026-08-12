<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DomainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Domain::query()->with('industry')->latest('first_seen_at');
        $query->when($request->filled('industry'), fn ($builder) => $builder->where(fn ($q) => $q->where('category', $request->string('industry')->toString())->orWhereHas('industry', fn ($industry) => $industry->where('slug', $request->string('industry')->toString()))));
        $query->when($request->filled('search'), fn ($builder) => $builder->where(fn ($q) => $q->where('domain', 'like', '%' . $request->string('search')->toString() . '%')->orWhere('category', 'like', '%' . $request->string('search')->toString() . '%')));
        $query->when($request->input('technology') === 'laravel', fn ($builder) => $builder->where('technology->laravel_detected', true));
        $query->when($request->boolean('laravel_detected'), fn ($builder) => $builder->where('technology->laravel_detected', true));
        $query->when($request->filled('minimum_laravel_confidence'), fn ($builder) => $builder->where('laravel_confidence', '>=', $request->integer('minimum_laravel_confidence')));
        $query->when($request->filled('laravel_confidence_label'), fn ($builder) => $builder->where('laravel_confidence_label', $request->string('laravel_confidence_label')->toString()));

        $domains = $query->paginate($this->perPage($request));
        return response()->json(['success' => true, 'data' => [
            'data' => collect($domains->items())->map(fn (Domain $domain) => $this->serializeDomain($domain))->values()->all(),
            'current_page' => $domains->currentPage(),
            'per_page' => $domains->perPage(),
            'last_page' => $domains->lastPage(),
            'from' => $domains->firstItem(),
            'to' => $domains->lastItem(),
            'total' => $domains->total(),
        ]]);
    }

    public function export(Request $request): StreamedResponse
    {
        $exportQuery = Domain::query()->with('industry')->latest('first_seen_at');
        if ($request->input('technology') === 'laravel' || $request->boolean('laravel_detected')) $exportQuery->where('technology->laravel_detected', true);
        if ($request->filled('minimum_laravel_confidence')) $exportQuery->where('laravel_confidence', '>=', $request->integer('minimum_laravel_confidence'));
        if ($request->filled('laravel_confidence_label')) $exportQuery->where('laravel_confidence_label', $request->string('laravel_confidence_label')->toString());
        $domains = $exportQuery->cursor();
        return response()->streamDownload(function () use ($domains): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['domain', 'category', 'source', 'country', 'status', 'response_code', 'response_time', 'quality_score', 'laravel_detected', 'laravel_confidence', 'laravel_confidence_label', 'laravel_signals', 'detection_method', 'technology_checked_at', 'last_checked']);
            foreach ($domains as $domain) fputcsv($handle, [$domain->domain, $domain->category ?: $domain->industry?->name, $domain->source, $domain->country_code, $domain->status, $domain->response_code, $domain->response_time, $domain->quality_score, ($domain->technology['laravel_detected'] ?? false) ? 'true' : 'false', $domain->laravel_confidence, $domain->laravel_confidence_label, json_encode($domain->technology['laravel_signals'] ?? []), json_encode($domain->technology['detection_method'] ?? []), $domain->technology_checked_at?->toIso8601String(), $domain->last_checked?->toIso8601String()]);
            fclose($handle);
        }, 'domain-export.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="domain-export.csv"']);
    }

    private function serializeDomain(Domain $domain): array
    {
        return [
            'id' => (string) $domain->id,
            'domain' => $domain->domain,
            'category' => $domain->category ?: $domain->industry?->name,
            'industry' => $domain->industry ? ['id' => (string) $domain->industry->id, 'name' => $domain->industry->name, 'slug' => $domain->industry->slug] : null,
            'source' => $domain->source,
            'country_code' => $domain->country_code,
            'status' => in_array($domain->status, Domain::STATUSES, true) ? $domain->status : 'pending',
            'quality_score' => (int) $domain->quality_score,
            'technology' => $domain->technology,
            'laravel_detected' => (bool) ($domain->technology['laravel_detected'] ?? false),
            'laravel_confidence' => $domain->laravel_confidence !== null ? (int) $domain->laravel_confidence : null,
            'laravel_confidence_label' => $domain->laravel_confidence_label,
            'laravel_signals' => $domain->technology['laravel_signals'] ?? [],
            'detection_method' => $domain->technology['detection_method'] ?? [],
            'technology_checked_at' => optional($domain->technology_checked_at)->toISOString(),
            'response_code' => $domain->response_code !== null ? (int) $domain->response_code : null,
            'response_time' => $domain->response_time !== null ? (int) $domain->response_time : null,
            'last_checked' => optional($domain->last_checked)->toISOString(),
        ];
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 50), 1), 100);
    }
}
