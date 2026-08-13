<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Discovery\AwsAgentCoreWebSearchProvider;
use Illuminate\Http\JsonResponse;

final class DiscoveryProviderController
{
    public function show(AwsAgentCoreWebSearchProvider $provider): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['provider' => $provider->key(), ...$provider->diagnostic()]]);
    }
}
