<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class VerifyPortalOrigin {
 public function handle(Request $request,Closure $next) {
  if($request->is('api/*')&&!$request->isMethodSafe()){
   // Reject browser cross-origin writes and simple form submissions.
   abort_unless($request->isJson(),415,'JSON requests are required.');
   $origin=$request->header('Origin');
   $trusted=array_filter([parse_url(config('app.url'),PHP_URL_HOST),$request->getHost()]);
   if($origin)abort_unless(in_array(parse_url($origin,PHP_URL_HOST),$trusted,true)&&!in_array($request->header('Sec-Fetch-Site'),['cross-site'],true),403,'Cross-origin requests are not allowed.');
  }
  return $next($request);
 }
}
