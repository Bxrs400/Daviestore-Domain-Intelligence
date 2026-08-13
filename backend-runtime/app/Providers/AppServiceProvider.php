<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Discovery\AwsAgentCoreWebSearchProvider;
use App\Services\Discovery\DiscoveryProviderRegistry;
use App\Services\Discovery\GitHubDiscoveryProvider;
use App\Services\Discovery\GoogleCustomSearchProvider;
use App\Services\Discovery\LocalSeedDiscoveryProvider;
use App\Services\Discovery\WikidataDiscoveryProvider;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            DiscoveryProviderRegistry::class,
            fn ($app) => new DiscoveryProviderRegistry([
                $app->make(LocalSeedDiscoveryProvider::class),
                $app->make(GoogleCustomSearchProvider::class),
                $app->make(WikidataDiscoveryProvider::class),
                $app->make(GitHubDiscoveryProvider::class),
                $app->make(AwsAgentCoreWebSearchProvider::class),
            ])
        );
    }

    public function boot(): void {}
}