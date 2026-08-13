<?php

use App\Http\Controllers\Api\CollectionJobController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\DiscoveryProviderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('collection-jobs', [CollectionJobController::class, 'store']);
    Route::get('collection-jobs/{job}', [CollectionJobController::class, 'show']);
    Route::get('collection-jobs/{job}/logs', [CollectionJobController::class, 'logs']);
    Route::post('collection-jobs/{job}/pause', [CollectionJobController::class, 'pause']);
    Route::post('collection-jobs/{job}/resume', [CollectionJobController::class, 'resume']);

    Route::get('discovery/providers/aws', [DiscoveryProviderController::class, 'show']);

    Route::get('domains', [DomainController::class, 'index']);
    Route::get('domains/export', [DomainController::class, 'export']);
});
