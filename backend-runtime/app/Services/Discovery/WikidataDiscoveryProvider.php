<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;

final class WikidataDiscoveryProvider implements DiscoveryProviderInterface
{
    public function key(): string
    {
        return 'wikidata';
    }

    public function search(string $query, array $options = []): array
    {
        $userAgent = config(
            'domain_discovery.user_agent',
            'DomainIntelCrawler/1.0'
        );

        $request = Http::timeout(
            (int) config('domain_discovery.timeout_seconds', 10)
        )
            ->retry(2, 250)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => $userAgent,
            ]);

        $response = $request->get(
            'https://www.wikidata.org/w/api.php',
            [
                'action' => 'wbsearchentities',
                'search' => $query,
                'language' => $options['language'] ?? 'en',
                'format' => 'json',
                'limit' => min(
                    20,
                    (int) ($options['provider_limit'] ?? 10)
                ),
            ]
        );

        if (! $response->successful()) {
            return [];
        }

        $entityIds = collect($response->json('search', []))
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if ($entityIds === []) {
            return [];
        }

        $entityResponse = $request->get(
            'https://www.wikidata.org/w/api.php',
            [
                'action' => 'wbgetentities',
                'ids' => implode('|', $entityIds),
                'props' => 'claims|labels|descriptions',
                'languages' => $options['language'] ?? 'en',
                'format' => 'json',
            ]
        );

        if (! $entityResponse->successful()) {
            return [];
        }

        $entities = $entityResponse->json('entities', []);
        $results = [];

        foreach ($entityIds as $entityId) {
            $entity = $entities[$entityId] ?? null;

            if (! is_array($entity)) {
                continue;
            }

            $websiteClaims = $entity['claims']['P856'] ?? [];

            foreach ($websiteClaims as $claim) {
                $url = $claim['mainsnak']['datavalue']['value'] ?? null;

                if (
                    ! is_string($url) ||
                    ! filter_var($url, FILTER_VALIDATE_URL)
                ) {
                    continue;
                }

                $host = parse_url($url, PHP_URL_HOST);

                if (! is_string($host) || $host === '') {
                    continue;
                }

                $host = strtolower(trim($host, '.'));
                $host = preg_replace('/^www\./i', '', $host);

                if (! is_string($host) || $host === '') {
                    continue;
                }

                $results[] = [
                    'domain' => $host,
                    'url' => $url,
                    'title' => $entity['labels']['en']['value'] ?? $entityId,
                    'snippet' => $entity['descriptions']['en']['value'] ?? null,
                    'provider' => $this->key(),
                    'metadata' => [
                        'entity_id' => $entityId,
                        'query' => $query,
                    ],
                ];
            }
        }

        return $results;
    }
}