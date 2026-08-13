<?php

declare(strict_types=1);

namespace App\Services\Discovery;

final class LocalSeedDiscoveryProvider implements DiscoveryProviderInterface
{
    public function key(): string
    {
        return 'local_seed';
    }

    public function search(string $query, array $options = []): array
    {
        $seeds = $options['seed_urls'] ?? [];
        $results = [];

        foreach ($seeds as $seed) {
            $url = trim((string) $seed);

            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                continue;
            }

            $host = strtolower(trim($host, '.'));

            // Normalize common www host prefix.
            $host = preg_replace('/^www\./i', '', $host);

            if (! is_string($host) || $host === '') {
                continue;
            }

            $results[] = [
                'domain' => $host,
                'url' => $url,
                'title' => null,
                'snippet' => $query,
                'provider' => $this->key(),
                'metadata' => [
                    'query' => $query,
                ],
            ];
        }

        return $results;
    }
}