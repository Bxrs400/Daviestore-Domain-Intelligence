<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class WebsiteCrawlerService
{
    public function __construct(
        private readonly DomainDiscoveryService $discovery
    ) {}

    public function crawl(string $url, int $timeout = 8): array
    {
        $url = $this->normalizeUrl($url);
        $started = microtime(true);

        try {
            $response = Http::withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'DomainIntelBot/1.0',
            ])
                ->timeout($timeout)
                ->connectTimeout(4)
                ->get($url);

            $html = $response->body();

            $crawler = new Crawler($html, $url);

            $links = $crawler
                ->filter('a[href]')
                ->each(fn (Crawler $node) => $node->attr('href'));

            $absoluteLinks = collect($links)
                ->filter()
                ->map(fn (string $link) => $this->absolutize($link, $url))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $setCookie = $response->header('Set-Cookie', '');

            if (is_array($setCookie)) {
                $cookies = implode('; ', $setCookie);
            } else {
                $cookies = (string) $setCookie;
            }

            return [
                'url' => $url,
                'status' => $response->status(),
                'response_time' => (int) round(
                    (microtime(true) - $started) * 1000
                ),

                // Required by LaravelTechnologyDetectionService
                'headers' => $response->headers(),
                'cookies' => $cookies,
                'html' => $html,

                'title' => trim(
                    $crawler->filter('title')->count()
                        ? $crawler->filter('title')->text()
                        : ''
                ),

                'description' => trim(
                    $crawler->filter('meta[name="description"]')->count()
                        ? (string) $crawler
                            ->filter('meta[name="description"]')
                            ->attr('content')
                        : ''
                ),

                'headings' => $crawler
                    ->filter('h1, h2, h3')
                    ->each(fn (Crawler $node) => trim($node->text())),

                'links' => $absoluteLinks,

                'body' => Str::limit(
                    trim(
                        preg_replace(
                            '/\s+/',
                            ' ',
                            $crawler->filter('body')->count()
                                ? $crawler->filter('body')->text()
                                : ''
                        )
                    ),
                    12000,
                    ''
                ),
            ];
        } catch (\Throwable $exception) {
            return [
                'url' => $url,
                'status' => 0,
                'response_time' => (int) round(
                    (microtime(true) - $started) * 1000
                ),
                'headers' => [],
                'cookies' => '',
                'html' => '',
                'title' => '',
                'description' => '',
                'headings' => [],
                'links' => [],
                'body' => '',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function normalizeUrl(string $url): string
    {
        return rtrim(
            Str::startsWith($url, ['http://', 'https://'])
                ? $url
                : "https://{$url}",
            '/'
        );
    }

    public function rootDomain(string $url): ?string
    {
        return $this->discovery->extractRootDomain($url);
    }

    private function absolutize(string $link, string $base): ?string
    {
        if (Str::startsWith(
            $link,
            ['mailto:', 'tel:', 'javascript:', '#']
        )) {
            return null;
        }

        if (Str::startsWith($link, '//')) {
            return 'https:' . $link;
        }

        if (filter_var($link, FILTER_VALIDATE_URL)) {
            return $link;
        }

        return rtrim($base, '/')
            . (Str::startsWith($link, '/') ? $link : '/' . $link);
    }
}