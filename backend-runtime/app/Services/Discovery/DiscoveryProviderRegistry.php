<?php

declare(strict_types=1);

namespace App\Services\Discovery;

final class DiscoveryProviderRegistry
{
    /** @param iterable<DiscoveryProviderInterface> $providers */
    public function __construct(private readonly iterable $providers) {}

    /** @return array<string, DiscoveryProviderInterface> */
    public function all(): array
    {
        $providers = [];
        foreach ($this->providers as $provider) $providers[$provider->key()] = $provider;
        return $providers;
    }
}
