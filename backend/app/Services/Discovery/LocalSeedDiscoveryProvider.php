<?php

declare(strict_types=1);

namespace App\Services\Discovery;

final class LocalSeedDiscoveryProvider implements DiscoveryProviderInterface
{
    public function key(): string { return 'local_seed'; }

    public function search(string $query, array $options = []): array
    {
        $seeds = $options['seed_urls'] ?? [];
        $results = [];
        foreach ($seeds as $seed) {
            $url = trim((string) $seed);
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) continue;
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host) continue;
            $results[] = ['domain' => strtolower($host), 'url' => $url, 'title' => null, 'snippet' => $query, 'provider' => $this->key(), 'metadata' => ['query' => $query]];
        }
        return $results;
    }
}
