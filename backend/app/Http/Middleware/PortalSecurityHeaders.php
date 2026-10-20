<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class PortalSecurityHeaders {
 public function handle(Request $request,Closure $next){
  $response=$next($request);
  $response->headers->set('X-Content-Type-Options','nosniff');
  $response->headers->set('X-Frame-Options','DENY');
  $response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');
  if(!app()->environment(['local','testing'])){
   $response->headers->set('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'");
   if($request->isSecure())$response->headers->set('Strict-Transport-Security','max-age=31536000');
  }
  return $response;
 }
}
