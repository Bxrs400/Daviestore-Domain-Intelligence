<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DomainValidationService
{
    public function validate(string $domain, int $timeout = 8): array
    {
        $started = microtime(true);
        $host = parse_url(str_starts_with($domain, 'http') ? $domain : "https://{$domain}", PHP_URL_HOST) ?: $domain;
        if (! checkdnsrr($host, 'A') && ! checkdnsrr($host, 'AAAA') && ! checkdnsrr($host, 'CNAME')) return ['status' => 'rejected', 'response_code' => null, 'response_time' => null, 'url' => "https://{$host}", 'error' => 'DNS resolution failed'];
        $lastError = null;
        foreach (["https://{$host}", "http://{$host}"] as $url) {
            try {
                $response = Http::timeout($timeout)->connectTimeout(4)->withHeaders(['User-Agent' => 'DomainIntelBot/1.0'])->get($url);
                $time = (int) round((microtime(true) - $started) * 1000);
                return ['status' => $response->successful() || $response->redirect() ? 'verified' : 'rejected', 'response_code' => $response->status(), 'response_time' => $time, 'url' => $url, 'error' => $response->successful() ? null : "HTTP {$response->status()}"];
            } catch (\Throwable $exception) { $lastError = $exception->getMessage(); }
        }
        return ['status' => 'rejected', 'response_code' => null, 'response_time' => (int) round((microtime(true) - $started) * 1000), 'url' => "https://{$host}", 'error' => $lastError ?: 'HTTP request failed'];
    }
}
