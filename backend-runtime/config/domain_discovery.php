<?php

return [
    'default_providers' => array_values(array_filter(array_map(
        'trim',
        explode(
            ',',
            env(
                'DOMAIN_DISCOVERY_DEFAULT_PROVIDERS',
                'local_seed,aws_agentcore_web_search,wikidata,github'
            )
        )
    ))),

    'google_key' => env('GOOGLE_CUSTOM_SEARCH_KEY'),
    'google_cx' => env('GOOGLE_CUSTOM_SEARCH_CX'),

    'github_token' => env('GITHUB_TOKEN'),

    'user_agent' => env('CRAWLER_USER_AGENT', 'DomainIntel/1.0'),

    'timeout_seconds' => (int) env('DOMAIN_DISCOVERY_TIMEOUT', 10),
    'max_results' => (int) env('DOMAIN_DISCOVERY_MAX_RESULTS', 500),
    'max_queries' => (int) env('DOMAIN_DISCOVERY_MAX_QUERIES', 24),

    'default_quality_threshold' => (int) env(
        'DOMAIN_DISCOVERY_DEFAULT_QUALITY_THRESHOLD',
        70
    ),

    'default_target' => (int) env(
        'DOMAIN_DISCOVERY_DEFAULT_TARGET',
        100
    ),

    'safety_multiplier' => (int) env(
        'DOMAIN_DISCOVERY_SAFETY_MULTIPLIER',
        5
    ),

    'aws' => [
        'enabled' => (bool) env(
            'AWS_AGENTCORE_WEB_SEARCH_ENABLED',
            false
        ),

        'region' => env(
            'AWS_REGION',
            'us-east-1'
        ),

        'gateway_url' => env(
            'AWS_AGENTCORE_GATEWAY_URL'
        ),

        'gateway_id' => env(
            'AWS_AGENTCORE_GATEWAY_ID'
        ),

        'auth_mode' => env(
            'AWS_AGENTCORE_AUTH_MODE',
            'iam'
        ),

        'service' => env(
            'AWS_AGENTCORE_SERVICE',
            'bedrock-agentcore'
        ),

        'web_search_tool' => env(
            'AWS_AGENTCORE_WEB_SEARCH_TOOL',
            'WebSearch'
        ),

        'gateway_token' => env(
            'AWS_AGENTCORE_GATEWAY_TOKEN'
        ),

        'timeout_seconds' => (int) env(
            'AWS_AGENTCORE_TIMEOUT',
            env('DOMAIN_DISCOVERY_TIMEOUT', 10)
        ),
    ],
];