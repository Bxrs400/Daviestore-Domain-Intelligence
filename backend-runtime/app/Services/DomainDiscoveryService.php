<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Discovery\DiscoveryProviderRegistry;
use Illuminate\Support\Str;

final class DomainDiscoveryService
{
    public function __construct(private readonly DiscoveryProviderRegistry $providers) {}

    /** @return array{queries:list<string>, urls:list<string>, domains:list<string>, candidates:list<array>, provider_stats:array<string, int>} */
    public function discover(array $options): array
    {
        $keywords = collect($options['keywords'] ?? [])->merge($options['categories'] ?? [])->filter(fn ($value) => is_string($value) && trim($value) !== '')->map(fn ($value) => trim($value))->unique()->values();
        $queries = $this->expandQueries($keywords->all(), $options);
        $seedUrls = collect($options['seed_urls'] ?? [])->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL))->values()->all();
        $enabled = array_values(array_intersect($options['providers'] ?? config('domain_discovery.default_providers', ['local_seed']), array_keys($this->providers->all())));
        $candidates = [];
        $providerStats = [];
        foreach ($enabled as $providerKey) {
            $provider = $this->providers->all()[$providerKey];
            $providerStats[$providerKey] = 0;
            foreach ($queries as $query) {
                try {
                    foreach ($provider->search($query, [...$options, 'seed_urls' => $seedUrls]) as $candidate) {
                        if (($candidate['domain'] ?? '') === '') continue;
                        $candidate['domain'] = $this->extractRootDomain($candidate['domain']);
                        if ($candidate['domain']) { $candidates[] = $candidate; $providerStats[$providerKey]++; }
                    }
                } catch (\Throwable) { continue; }
            }
        }
        foreach ($seedUrls as $url) $candidates[] = ['domain' => $this->extractRootDomain($url), 'url' => $url, 'title' => null, 'snippet' => null, 'provider' => 'local_seed', 'metadata' => ['seed' => true]];
        $candidates = collect($candidates)->filter(fn ($candidate) => $candidate['domain'] && $this->allowed($candidate['domain'], $options))->unique('domain')->take((int) ($options['max_results'] ?? config('domain_discovery.max_results', 500)))->values()->all();
        return ['queries' => $queries, 'urls' => $seedUrls, 'domains' => collect($candidates)->pluck('domain')->values()->all(), 'candidates' => $candidates, 'provider_stats' => $providerStats];
    }

    private function expandQueries(array $keywords, array $options): array
    {
        if (!$keywords) $keywords = ['business directory', 'software companies', 'technology startups'];
        $queries = collect($keywords)->flatMap(fn (string $keyword) => [$keyword, "{$keyword} companies", "{$keyword} directory"])->unique()->take((int) ($options['max_queries'] ?? config('domain_discovery.max_queries', 24)))->values()->all();
        return $queries;
    }

    private function allowed(string $domain, array $options): bool
    {
        foreach (($options['exclude_domains'] ?? []) as $excluded) if (Str::endsWith($domain, strtolower(ltrim((string) $excluded, '.')))) return false;
        $includes = array_filter($options['include_domains'] ?? []);
        return !$includes || collect($includes)->contains(fn ($included) => Str::endsWith($domain, strtolower(ltrim((string) $included, '.'))));
    }

    public function extractRootDomain(string $url): ?string
    {
        $host = parse_url(Str::startsWith($url, ['http://', 'https://']) ? $url : "https://{$url}", PHP_URL_HOST);
        if (!is_string($host)) return null;
        $host = preg_replace('/^www\./', '', strtolower(trim($host, '.')));
        if (!$host || filter_var($host, FILTER_VALIDATE_IP)) return $host ?: null;
        $parts = explode('.', $host);
        if (count($parts) < 2) return null;
        $suffix3 = implode('.', array_slice($parts, -3));
        $multiPartSuffixes = ['co.uk', 'com.au', 'co.nz', 'com.br', 'co.jp', 'co.za', 'com.mx'];
        return in_array(implode('.', array_slice($parts, -2)), $multiPartSuffixes, true) ? $suffix3 : implode('.', array_slice($parts, -2));
    }
}
