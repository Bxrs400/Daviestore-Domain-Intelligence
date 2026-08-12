<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class LaravelTechnologyDetectionService
{
    /**
     * @return array{
     *   laravel_detected: bool,
     *   laravel_confidence: int,
     *   laravel_confidence_label: string,
     *   laravel_signals: array<int, array<string, mixed>>,
     *   detection_method: array<int, string>,
     *   checked_at: string
     * }
     */
    public function detect(string $url, array $page): array
    {
        $signals = [];
        $methods = [];

        $headers = collect($page['headers'] ?? [])
            ->mapWithKeys(function ($value, $key) {
                $normalized = is_array($value)
                    ? implode('; ', $value)
                    : (string) $value;

                return [
                    strtolower((string) $key) => strtolower($normalized),
                ];
            });

        $html = strtolower((string) ($page['html'] ?? ''));
        $cookies = strtolower((string) ($page['cookies'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | Strong signals
        |--------------------------------------------------------------------------
        */

        if (str_contains($cookies, 'laravel_session')) {
            $signals[] = [
                'type' => 'cookie',
                'evidence' => 'Public response exposed a laravel_session cookie',
                'weight' => 75,
            ];

            $methods[] = 'response_cookie';
        }

        if (str_contains($cookies, 'xsrf-token')) {
            $signals[] = [
                'type' => 'cookie',
                'evidence' => 'Public response exposed an XSRF-TOKEN cookie',
                'weight' => 20,
            ];

            $methods[] = 'response_cookie';
        }

        foreach ([
            'x-powered-by',
            'server',
            'x-generator',
        ] as $headerName) {
            $value = $headers->get($headerName, '');

            if (str_contains($value, 'laravel')) {
                $signals[] = [
                    'type' => 'header',
                    'evidence' => "Public {$headerName} header contains Laravel",
                    'weight' => 65,
                ];

                $methods[] = 'response_header';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | HTML / asset fingerprints
        |--------------------------------------------------------------------------
        */

        $htmlFingerprints = [
            [
                'needle' => 'csrf-token',
                'weight' => 20,
                'evidence' => 'Public HTML contains a csrf-token meta marker',
            ],
            [
                'needle' => 'mix-manifest',
                'weight' => 35,
                'evidence' => 'Public HTML references Laravel Mix-related assets',
            ],
            [
                'needle' => '/build/assets/',
                'weight' => 20,
                'evidence' => 'Public HTML references build/assets paths commonly emitted by Laravel Vite builds',
            ],
            [
                'needle' => 'vite',
                'weight' => 10,
                'evidence' => 'Public HTML contains Vite-related asset markers',
            ],
            [
                'needle' => 'laravel',
                'weight' => 30,
                'evidence' => 'Public HTML contains a Laravel framework identifier',
            ],
            [
                'needle' => 'livewire',
                'weight' => 25,
                'evidence' => 'Public HTML contains Livewire-related markers',
            ],
            [
                'needle' => 'wire:',
                'weight' => 15,
                'evidence' => 'Public HTML contains Livewire wire:* attributes',
            ],
            [
                'needle' => 'inertia',
                'weight' => 15,
                'evidence' => 'Public HTML contains Inertia-related markers',
            ],
        ];

        foreach ($htmlFingerprints as $fingerprint) {
            if (str_contains($html, $fingerprint['needle'])) {
                $signals[] = [
                    'type' => 'html',
                    'evidence' => $fingerprint['evidence'],
                    'weight' => $fingerprint['weight'],
                ];

                $methods[] = 'html_fingerprint';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Combination bonuses
        |--------------------------------------------------------------------------
        |
        | These do not rely on one weak signal alone. They only add confidence
        | when multiple passive public indicators appear together.
        */

        $signalTypes = collect($signals)->pluck('type');

        $hasCookieSignal = $signalTypes->contains('cookie');
        $hasHeaderSignal = $signalTypes->contains('header');
        $hasHtmlSignal = $signalTypes->contains('html');

        if ($hasCookieSignal && $hasHtmlSignal) {
            $signals[] = [
                'type' => 'combination',
                'evidence' => 'Cookie and HTML Laravel-related indicators appeared together',
                'weight' => 15,
            ];

            $methods[] = 'combined_passive_signals';
        }

        if ($hasHeaderSignal && $hasHtmlSignal) {
            $signals[] = [
                'type' => 'combination',
                'evidence' => 'Header and HTML Laravel-related indicators appeared together',
                'weight' => 15,
            ];

            $methods[] = 'combined_passive_signals';
        }

        /*
        |--------------------------------------------------------------------------
        | Score
        |--------------------------------------------------------------------------
        */

        $score = min(
            100,
            (int) collect($signals)->sum('weight')
        );

        $label = match (true) {
            $score >= 85 => 'Confirmed / very high confidence',
            $score >= 70 => 'Likely',
            $score > 0 => 'Possible',
            default => 'No Laravel evidence',
        };

        return [
            'laravel_detected' => $score >= 70,
            'laravel_confidence' => $score,
            'laravel_confidence_label' => $label,
            'laravel_signals' => $signals,
            'detection_method' => array_values(array_unique($methods)),
            'checked_at' => Carbon::now()->toISOString(),
        ];
    }
}