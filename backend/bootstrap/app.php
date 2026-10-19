<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php',commands: __DIR__.'/../routes/console.php',health:'/up')
 ->withMiddleware(function(Middleware $middleware){
  // Same-origin session API: JSON API clients receive no redirects.
  $middleware->validateCsrfTokens(except:['api/*']);
  $middleware->appendToGroup('web',\App\Http\Middleware\VerifyPortalOrigin::class);
 })
 ->withExceptions(function(Exceptions $exceptions){$exceptions->shouldRenderJsonWhen(fn($request)=>$request->is('api/*'));})
 ->create();
