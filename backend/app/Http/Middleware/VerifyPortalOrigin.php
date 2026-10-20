<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class VerifyPortalOrigin {
 private function origin(string $url): ?string {
  $parts=parse_url($url);if(!$parts||!isset($parts['scheme'],$parts['host'])||!in_array(strtolower($parts['scheme']),['http','https'],true)||isset($parts['user'])||isset($parts['pass']))return null;
  $scheme=strtolower($parts['scheme']);$port=$parts['port']??($scheme==='https'?443:80);
  return $scheme.'://'.strtolower($parts['host']).':'.$port;
 }
 public function handle(Request $request,Closure $next) {
  if($request->is('api/*')&&!$request->isMethodSafe()){
   // Reject browser cross-origin writes and simple form submissions.
   abort_unless($request->isJson(),415,'JSON requests are required.');
   $origin=$request->header('Origin');
   $trusted=array_filter(array_map(fn($url)=>$this->origin(trim($url)),[config('app.url'),...config('security.trusted_origins',[])]));
   abort_if($request->header('Sec-Fetch-Site')==='cross-site',403,'Cross-origin requests are not allowed.');
   if($origin){
    abort_unless(in_array($this->origin($origin),$trusted,true),403,'Cross-origin requests are not allowed.');
   }else{
    $referer=$request->header('Referer');
    abort_unless($referer&&in_array($this->origin($referer),$trusted,true),403,'A trusted browser origin or referrer is required.');
   }
  }
  return $next($request);
 }
}
