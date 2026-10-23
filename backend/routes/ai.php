<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Integrations\Mcp\PortalServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// No web/session middleware: bearer credentials cannot authorize ordinary API writes.
// Keep registration stable for route caches; the middleware enforces the opt-in flag.
Route::middleware(['throttle:120,1', AuthenticateMcp::class, 'throttle:portal-mcp'])->group(function () {
    Mcp::web('/api/mcp', PortalServer::class);
});
