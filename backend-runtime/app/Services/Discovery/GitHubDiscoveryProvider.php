<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;

final class GitHubDiscoveryProvider implements DiscoveryProviderInterface
{
    public function key(): string
    {
        return 'github';
    }

    public function search(string $query, array $options = []): array
    {
        $request = Http::timeout(
            (int) config('domain_discovery.timeout_seconds', 10)
        )
            ->retry(2, 250)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => config(
                    'domain_discovery.user_agent',
                    'DomainIntelCrawler/1.0'
                ),
            ]);

        if ($token = config('domain_discovery.github_token')) {
            $request = $request->withToken($token);
        }

        $response = $request->get(
            'https://api.github.com/search/repositories',
            [
                'q' => $query,
                'per_page' => min(
                    30,
                    (int) ($options['provider_limit'] ?? 10)
                ),
            ]
        );

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('items', []))
            ->map(function (array $item): ?array {
                $homepage = trim((string) ($item['homepage'] ?? ''));

                // Skip repos without a real external homepage.
                if (
                    $homepage === '' ||
                    ! filter_var($homepage, FILTER_VALIDATE_URL)
                ) {
                    return null;
                }

                $host = parse_url($homepage, PHP_URL_HOST);

                if (! is_string($host) || $host === '') {
                    return null;
                }

                $host = strtolower(trim($host, '.'));
                $host = preg_replace('/^www\./i', '', $host);

                // Do not treat GitHub repo pages as business websites.
                if ($host === 'github.com') {
                    return null;
                }

                return [
                    'domain' => $host,
                    'url' => $homepage,
                    'title' => $item['full_name'] ?? null,
                    'snippet' => $item['description'] ?? null,
                    'provider' => $this->key(),
                    'metadata' => [
                        'stars' => $item['stargazers_count'] ?? 0,
                        'language' => $item['language'] ?? null,
                        'repository_url' => $item['html_url'] ?? null,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}