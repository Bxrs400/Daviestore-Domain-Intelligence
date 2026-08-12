<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Discovery\LocalSeedDiscoveryProvider;
use PHPUnit\Framework\TestCase;

final class DomainDiscoveryServiceTest extends TestCase
{
    public function test_local_seed_provider_returns_normalizable_candidates(): void
    {
        $results = (new LocalSeedDiscoveryProvider())->search('laravel companies', ['seed_urls' => ['https://www.example.co.uk/path']]);
        self::assertSame('example.co.uk', $results[0]['domain']);
        self::assertSame('local_seed', $results[0]['provider']);
    }

    public function test_provider_does_not_fabricate_unconfigured_results(): void
    {
        $this->assertSame([], (new LocalSeedDiscoveryProvider())->search('missing', []));
    }
}
