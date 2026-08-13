<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AwsAgentCoreWebSearchProvider implements DiscoveryProviderInterface
{
    private ?array $lastDiagnostic = null;

    public function key(): string
    {
        return 'aws_agentcore_web_search';
    }

    public function status(): string
    {
        return $this->diagnostic()['status'];
    }

    public function search(string $query, array $options = []): array
    {
        $base = $this->diagnostic();

        if (!in_array($base['status'], ['AVAILABLE', 'READY'], true)) {
            return [];
        }

        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            /*
             * Gateway sessions are not required for this provider.
             *
             * First ask the AgentCore MCP Gateway which tools are available.
             */
            $tools = $this->mcp(
                'tools/list',
                [],
                $headers
            );

            $base = [
                ...$base,
                'gateway_reachable' => true,
                'mcp_initialized' => true,
            ];

            $toolName = $this->discoverWebSearchTool($tools);

            if ($toolName === null) {
                $this->lastDiagnostic = [
                    ...$base,
                    'status' => 'WEBSEARCH_TOOL_NOT_FOUND',
                    'websearch_tool_discovered' => false,
                    'discovered_tool_name' => null,
                    'last_error' => 'WebSearch was not returned by tools/list.',
                ];

                return [];
            }

            $searchQuery = $this->buildSearchQuery(
                $query,
                $options
            );

            /*
             * AWS Web Search arguments.
             *
             * Keep location context inside the natural-language query.
             * Do not send custom unsupported fields to the WebSearch tool.
             */
            $result = $this->mcp(
                'tools/call',
                [
                    'name' => $toolName,
                    'arguments' => [
                        'query' => mb_substr($searchQuery, 0, 200),
                        'maxResults' => min(
                            max(
                                (int) ($options['provider_limit'] ?? 10),
                                1
                            ),
                            25
                        ),
                    ],
                ],
                $headers
            );

            if (($result['isError'] ?? false) === true) {
                throw new RuntimeException(
                    $this->extractMcpErrorMessage($result)
                );
            }

            $normalized = $this->normalize(
                $result,
                $searchQuery,
                $toolName
            );

            $this->lastDiagnostic = [
                ...$base,
                'status' => 'READY',
                'gateway_reachable' => true,
                'mcp_initialized' => true,
                'websearch_tool_discovered' => true,
                'discovered_tool_name' => $toolName,
                'last_error' => null,
            ];

            return $normalized;
        } catch (Throwable $error) {
            $this->lastDiagnostic = [
                ...$base,
                'status' => 'GATEWAY_ERROR',
                'gateway_reachable' => false,
                'mcp_initialized' => false,
                'websearch_tool_discovered' => false,
                'discovered_tool_name' => null,
                'last_error' => $error->getMessage(),
            ];

            return [];
        }
    }

    public function diagnostic(): array
    {
        if ($this->lastDiagnostic !== null) {
            return $this->lastDiagnostic;
        }

        $mode = (string) config(
            'domain_discovery.aws.auth_mode',
            'iam'
        );

        $gateway = trim(
            (string) config(
                'domain_discovery.aws.gateway_url',
                ''
            )
        );

        $enabled = (bool) config(
            'domain_discovery.aws.enabled',
            false
        );

        $credentialsAvailable = $mode === 'jwt'
            ? (bool) config(
                'domain_discovery.aws.gateway_token'
            )
            : $this->hasAwsCredentials();

        if (!$enabled || $gateway === '') {
            $status = 'REQUIRES_CONFIGURATION';
        } elseif (!$credentialsAvailable) {
            $status = 'AWS_CREDENTIALS_UNAVAILABLE';
        } else {
            $status = 'AVAILABLE';
        }

        return [
            'status' => $status,
            'enabled' => $enabled,
            'auth_mode' => $mode,
            'region' => (string) config(
                'domain_discovery.aws.region',
                ''
            ),
            'gateway_configured' => $gateway !== '',
            'aws_credential_chain_available' => $this->hasAwsCredentials(),
            'authentication_available' => $credentialsAvailable,
            'gateway_reachable' => false,
            'mcp_initialized' => false,
            'websearch_tool_discovered' => false,
            'discovered_tool_name' => null,
            'last_error' => null,
        ];
    }

    private function mcp(
        string $method,
        array $params,
        array $headers = []
    ): array {
        $url = trim(
            (string) config(
                'domain_discovery.aws.gateway_url',
                ''
            )
        );

        if ($url === '') {
            throw new RuntimeException(
                'AWS AgentCore Gateway URL is not configured.'
            );
        }

        $body = json_encode(
            [
                'jsonrpc' => '2.0',
                'id' => uniqid('domainintel_', true),
                'method' => $method,
                'params' => $params,
            ],
            JSON_THROW_ON_ERROR
        );

        $requestHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $request = new PsrRequest(
            'POST',
            $url,
            $requestHeaders,
            $body
        );

        $authMode = (string) config(
            'domain_discovery.aws.auth_mode',
            'iam'
        );

        if ($authMode === 'iam') {
            $credentialProvider = CredentialProvider::defaultProvider();

            $credentials = $credentialProvider()
                ->wait();

            $region = (string) config(
                'domain_discovery.aws.region',
                'us-east-1'
            );

            $service = (string) config(
                'domain_discovery.aws.service',
                'bedrock-agentcore'
            );

            $signer = new SignatureV4(
                $service,
                $region
            );

            $request = $signer->signRequest(
                $request,
                $credentials
            );

            foreach ($request->getHeaders() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }
        } elseif ($authMode === 'jwt') {
            $token = trim(
                (string) config(
                    'domain_discovery.aws.gateway_token',
                    ''
                )
            );

            if ($token === '') {
                throw new RuntimeException(
                    'AWS AgentCore Gateway JWT token is not configured.'
                );
            }

            $headers['Authorization'] = 'Bearer '.$token;
        } else {
            throw new RuntimeException(
                'Unsupported AWS AgentCore authentication mode: '.$authMode
            );
        }

        $timeout = (int) config(
            'domain_discovery.aws.timeout_seconds',
            config('domain_discovery.timeout_seconds', 10)
        );

        $response = Http::timeout($timeout)
            ->withHeaders($headers)
            ->withBody(
                $body,
                'application/json'
            )
            ->post($url);

        if (!$response->successful()) {
            $responseBody = trim($response->body());

            throw new RuntimeException(
                'AgentCore Gateway returned HTTP '
                .$response->status()
                .($responseBody !== ''
                    ? ': '.$responseBody
                    : '')
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new RuntimeException(
                'AgentCore Gateway returned a non-JSON MCP response.'
            );
        }

        if (isset($json['error'])) {
            $message = is_array($json['error'])
                ? (string) (
                    $json['error']['message']
                    ?? json_encode($json['error'])
                )
                : (string) $json['error'];

            throw new RuntimeException(
                'MCP error: '.$message
            );
        }

        if (
            isset($json['result'])
            && is_array($json['result'])
        ) {
            return $json['result'];
        }

        return $json;
    }

    private function discoverWebSearchTool(array $tools): ?string
    {
        foreach ($tools['tools'] ?? [] as $tool) {
            if (!is_array($tool)) {
                continue;
            }

            $name = trim(
                (string) ($tool['name'] ?? '')
            );

            if ($name === '') {
                continue;
            }

            /*
             * Handles names such as:
             *
             * WebSearch
             * web_search
             * target___WebSearch
             * target-web-search
             */
            $normalizedName = strtolower(
                preg_replace(
                    '/[^a-z0-9]/i',
                    '',
                    $name
                ) ?? ''
            );

            if (str_contains($normalizedName, 'websearch')) {
                return $name;
            }

            $description = strtolower(
                (string) ($tool['description'] ?? '')
            );

            if (
                str_contains($description, 'web search')
                || str_contains($description, 'search the web')
            ) {
                return $name;
            }
        }

        return null;
    }

    private function buildSearchQuery(
        string $query,
        array $options
    ): string {
        $query = trim($query);

        if (
            ($options['location_scope'] ?? 'worldwide')
            === 'worldwide'
        ) {
            return $query;
        }

        $locationParts = [];

        foreach ([
            $options['city'] ?? null,
            $options['region'] ?? null,
            $options['country'] ?? null,
        ] as $part) {
            if (
                !is_string($part)
                || trim($part) === ''
            ) {
                continue;
            }

            $part = trim($part);

            /*
             * Don't append Munich/Germany again if the user already
             * included that location in the natural-language query.
             */
            if (stripos($query, $part) === false) {
                $locationParts[] = $part;
            }
        }

        /*
         * Use country_code only when no actual country name was supplied.
         */
        if (
            empty($options['country'])
            && !empty($options['country_code'])
            && is_string($options['country_code'])
        ) {
            $countryCode = trim($options['country_code']);

            if (
                $countryCode !== ''
                && stripos($query, $countryCode) === false
            ) {
                $locationParts[] = $countryCode;
            }
        }

        $locationParts = array_values(
            array_unique($locationParts)
        );

        if ($locationParts === []) {
            return $query;
        }

        return trim(
            $query.' in '.implode(', ', $locationParts)
        );
    }

    private function hasAwsCredentials(): bool
    {
        try {
            $provider = CredentialProvider::defaultProvider();

            $credentials = $provider()
                ->wait();

            return $credentials->getAccessKeyId() !== ''
                && $credentials->getSecretKey() !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function normalize(
        array $body,
        string $query,
        string $toolName
    ): array {
        $items = $this->extractResults($body);

        $normalized = [];
        $seenDomains = [];

        foreach ($items as $rank => $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = $item['url']
                ?? $item['link']
                ?? null;

            if (
                !is_string($url)
                || !filter_var(
                    $url,
                    FILTER_VALIDATE_URL
                )
            ) {
                continue;
            }

            $host = parse_url(
                $url,
                PHP_URL_HOST
            );

            if (
                !is_string($host)
                || trim($host) === ''
            ) {
                continue;
            }

            $domain = preg_replace(
                '/^www\./i',
                '',
                trim($host, '.')
            );

            if (
                !is_string($domain)
                || $domain === ''
            ) {
                continue;
            }

            $domain = Str::lower($domain);

            if (isset($seenDomains[$domain])) {
                continue;
            }

            $seenDomains[$domain] = true;

            $normalized[] = [
                'domain' => $domain,

                'url' => $url,

                'title' => $item['title']
                    ?? null,

                'snippet' => $item['text']
                    ?? $item['snippet']
                    ?? $item['description']
                    ?? null,

                'provider' => $this->key(),

                'metadata' => [
                    'query' => $query,
                    'tool' => $toolName,
                    'rank' => $rank + 1,
                    'published_date' => $item['publishedDate']
                        ?? $item['published_date']
                        ?? null,
                ],
            ];
        }

        return $normalized;
    }

    private function extractResults(array $body): array
    {
        if (
            isset($body['structuredContent']['results'])
            && is_array(
                $body['structuredContent']['results']
            )
        ) {
            return $body['structuredContent']['results'];
        }

        if (
            isset($body['results'])
            && is_array($body['results'])
        ) {
            return $body['results'];
        }

        if (
            isset($body['content'][0]['json']['results'])
            && is_array(
                $body['content'][0]['json']['results']
            )
        ) {
            return $body['content'][0]['json']['results'];
        }

        /*
         * MCP tools commonly return JSON encoded inside content[].text.
         */
        foreach ($body['content'] ?? [] as $content) {
            if (
                !is_array($content)
                || !isset($content['text'])
                || !is_string($content['text'])
            ) {
                continue;
            }

            $decoded = json_decode(
                $content['text'],
                true
            );

            if (!is_array($decoded)) {
                continue;
            }

            if (
                isset($decoded['results'])
                && is_array($decoded['results'])
            ) {
                return $decoded['results'];
            }

            if (
                isset($decoded['data']['results'])
                && is_array(
                    $decoded['data']['results']
                )
            ) {
                return $decoded['data']['results'];
            }
        }

        return [];
    }

    private function extractMcpErrorMessage(array $result): string
    {
        foreach ($result['content'] ?? [] as $content) {
            if (
                is_array($content)
                && isset($content['text'])
                && is_string($content['text'])
                && trim($content['text']) !== ''
            ) {
                return 'WebSearch MCP error: '.trim(
                    $content['text']
                );
            }
        }

        return 'AWS WebSearch returned an MCP tool error.';
    }
}