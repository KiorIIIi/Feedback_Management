<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
 public function register():void {}
 public function boot():void {
  \Illuminate\Support\Facades\RateLimiter::for('portal-mcp',fn($r)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(config('mcp.requests_per_minute'))->by('mcp:'.$r->attributes->get('mcp_authorization')->id));
  \App\Services\ProductionConfiguration::assertSafe([
   'app'=>config('app'),'session'=>config('session'),'database'=>config('database'),'security'=>config('security'),
   'demo_seed'=>config('security.demo_seed'),
  ]);
  \Illuminate\Support\Facades\RateLimiter::for('portal-login',fn($r)=>[
   \Illuminate\Cache\RateLimiting\Limit::perMinute(config('security.login_ip_per_minute'))->by('login-ip:'.$r->ip()),
   \Illuminate\Cache\RateLimiting\Limit::perMinute(config('security.login_account_per_minute'))->by('login-account:'.hash('sha256',mb_strtolower(trim((string)$r->input('email'))))),
  ]);
  \Illuminate\Support\Facades\RateLimiter::for('portal-feedback',fn($r)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(config('security.feedback_per_minute'))->by('feedback:'.($r->user()?->id??$r->ip())));
  \Illuminate\Support\Facades\RateLimiter::for('portal-reports',fn($r)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(config('security.report_per_minute'))->by('reports:'.$r->user()->id));
  \Illuminate\Support\Facades\RateLimiter::for('portal-mutations',fn($r)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(config('security.mutations_per_minute'))->by('mutations:'.$r->user()->id));
  \Illuminate\Support\Facades\RateLimiter::for('portal-reads',fn($r)=>$r->isMethodSafe()?\Illuminate\Cache\RateLimiting\Limit::perMinute(config('security.reads_per_minute'))->by('reads:'.$r->user()->id):\Illuminate\Cache\RateLimiting\Limit::none());
 }
}
