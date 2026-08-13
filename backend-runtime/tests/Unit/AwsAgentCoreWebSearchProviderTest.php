<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Discovery\AwsAgentCoreWebSearchProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AwsAgentCoreWebSearchProviderTest extends TestCase
{
    public function test_normalizes_gateway_results(): void
    {
        config()->set('domain_discovery.aws.enabled', true);
        config()->set('domain_discovery.aws.gateway_url', 'https://gateway.test/search');
        Http::fake(['gateway.test/*' => Http::response(['results' => [['link' => 'https://www.example.com/a', 'title' => 'Example', 'snippet' => 'Result']]])]);
        $results = app(AwsAgentCoreWebSearchProvider::class)->search('Caterpillar parts Munich');
        self::assertSame('example.com', $results[0]['domain']);
        self::assertSame('aws_agentcore_web_search', $results[0]['provider']);
    }

    public function test_returns_empty_when_not_configured(): void
    {
        config()->set('domain_discovery.aws.enabled', false);
        self::assertSame([], app(AwsAgentCoreWebSearchProvider::class)->search('test'));
    }
}
