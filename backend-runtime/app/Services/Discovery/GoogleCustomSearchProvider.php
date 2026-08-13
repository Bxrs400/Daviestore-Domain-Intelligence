<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;

final class GoogleCustomSearchProvider implements DiscoveryProviderInterface
{
    public function key(): string { return 'google'; }

    public function search(string $query, array $options = []): array
    {
        $key = (string) config('domain_discovery.google_key', '');
        $cx = (string) config('domain_discovery.google_cx', '');
        if ($key === '' || $cx === '') return [];
        $response = Http::timeout((int) config('domain_discovery.timeout_seconds', 10))->retry(2, 250)->get('https://www.googleapis.com/customsearch/v1', ['key' => $key, 'cx' => $cx, 'q' => $query, 'num' => min(10, (int) ($options['provider_limit'] ?? 10))]);
        if (!$response->successful()) return [];
        return collect($response->json('items', []))->map(function (array $item): array {
            $url = (string) ($item['link'] ?? '');
            return ['domain' => strtolower((string) parse_url($url, PHP_URL_HOST)), 'url' => $url, 'title' => $item['title'] ?? null, 'snippet' => $item['snippet'] ?? null, 'provider' => $this->key(), 'metadata' => ['display_link' => $item['displayLink'] ?? null]];
        })->filter(fn (array $item) => $item['domain'] !== '')->values()->all();
    }
}
