<?php

return [
    'default_providers' => array_values(array_filter(array_map('trim', explode(',', env('DOMAIN_DISCOVERY_DEFAULT_PROVIDERS', 'local_seed,google,wikidata,github'))))),
    'google_key' => env('GOOGLE_CUSTOM_SEARCH_KEY'),
    'google_cx' => env('GOOGLE_CUSTOM_SEARCH_CX'),
    'github_token' => env('GITHUB_TOKEN'),
    'user_agent' => env('CRAWLER_USER_AGENT', 'DomainIntel/1.0'),
    'timeout_seconds' => (int) env('DOMAIN_DISCOVERY_TIMEOUT', 10),
    'max_results' => (int) env('DOMAIN_DISCOVERY_MAX_RESULTS', 500),
    'max_queries' => (int) env('DOMAIN_DISCOVERY_MAX_QUERIES', 24),
];
