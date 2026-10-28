<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Integrations\Mcp\PortalServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware([\App\Http\Middleware\PrepareMcpOAuth::class, 'throttle:30,1'])->group(function () {
    Mcp::oauthRoutes(registration: false);
});
Route::prefix('oauth')->name('passport.')->middleware('throttle:30,1')->group(function () {
    Route::post('token', [\Laravel\Passport\Http\Controllers\AccessTokenController::class, 'issueToken'])->middleware(\App\Http\Middleware\PrepareMcpOAuth::class)->name('token');
    Route::middleware(['web', \App\Http\Middleware\PrepareMcpOAuth::class])->group(function () {
        Route::get('authorize', [\Laravel\Passport\Http\Controllers\AuthorizationController::class, 'authorize'])->name('authorizations.authorize');
        Route::post('authorize', [\Laravel\Passport\Http\Controllers\ApproveAuthorizationController::class, 'approve'])->middleware('auth')->name('authorizations.approve');
        Route::delete('authorize', [\Laravel\Passport\Http\Controllers\DenyAuthorizationController::class, 'deny'])->middleware('auth')->name('authorizations.deny');
    });
});

// No web/session middleware: bearer credentials cannot authorize ordinary API writes.
// Keep registration stable for route caches; the middleware enforces the opt-in flag.
Route::middleware(['throttle:120,1', AuthenticateMcp::class, 'throttle:portal-mcp'])->group(function () {
    Mcp::web('/api/mcp', PortalServer::class);
});
