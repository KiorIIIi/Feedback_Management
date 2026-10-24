<?php
namespace Tests\Feature;

use App\Http\Middleware\ConfiguredTrustProxies;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ProductionConfiguration;
use App\Services\RequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LatestAuditTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();}
 private function production():array {
  return ['app'=>['env'=>'production','debug'=>false,'url'=>'https://portal.example','key'=>'base64:'.base64_encode(random_bytes(32))],
   'session'=>['secure'=>true,'encrypt'=>true],
   'database'=>['default'=>'mysql','connections'=>['mysql'=>['database'=>'clsu_portal','username'=>'portal_app','password'=>'fixture-production-password']]],
   'security'=>['trusted_origins'=>[],'trusted_proxies'=>[]],'demo_seed'=>false,'mcp'=>['enabled'=>false]];
 }
 public function test_production_guard_accepts_secure_config_and_rejects_unsafe_fields():void {
  $safe=$this->production();ProductionConfiguration::assertSafe($safe);$this->addToAssertionCount(1);
  foreach(['app.debug'=>true,'app.url'=>'http://portal.example','app.key'=>'invalid','session.secure'=>false,'session.encrypt'=>false,'database.default'=>'sqlite','database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'','database.connections.mysql.database'=>'clsu_feedback_test','security.trusted_origins'=>['https://*'],'security.trusted_proxies'=>['*'],'demo_seed'=>true] as $field=>$value){
   $invalid=$safe;Arr::set($invalid,$field,$value);
   try{ProductionConfiguration::assertSafe($invalid);$this->fail('Unsafe production setting accepted: '.$field);}
   catch(\RuntimeException $error){$this->assertStringStartsWith('Unsafe production configuration:',$error->getMessage());$this->assertStringNotContainsString('fixture-production-password',$error->getMessage());}
  }
  $invalid=$safe;$invalid['mcp']['enabled']='off';
  try{ProductionConfiguration::assertSafe($invalid);$this->fail('Non-boolean MCP configuration accepted.');}
  catch(\RuntimeException $error){$this->assertStringContainsString('MCP_ENABLED',$error->getMessage());}
  $safe['mcp']['enabled']=true;ProductionConfiguration::assertSafe($safe);$this->addToAssertionCount(1);
  foreach(['local','testing'] as $environment){ProductionConfiguration::assertSafe(['app'=>['env'=>$environment]]);$this->addToAssertionCount(1);}
 }
 public function test_cors_does_not_authorize_unlisted_origins():void {
  $origin=config('app.url');$this->withHeaders(['Origin'=>$origin])->getJson('/api/me')->assertUnauthorized()->assertHeader('Access-Control-Allow-Origin',$origin)->assertHeaderMissing('Access-Control-Allow-Credentials');
  // The framework may emit a fixed single allowed origin; it must never echo
  // the unlisted origin. A mismatched header does not authorize browser access.
  $this->withHeaders(['Origin'=>'https://unlisted.example'])->getJson('/api/me')->assertUnauthorized()->assertHeader('Access-Control-Allow-Origin',$origin);
  $this->withHeaders(['Origin'=>$origin,'Access-Control-Request-Method'=>'POST','Access-Control-Request-Headers'=>'content-type'])->options('/api/feedback')->assertSuccessful()->assertHeader('Access-Control-Allow-Origin',$origin);
  $this->withHeaders(['Origin'=>'https://unlisted.example'])->options('/api/feedback')->assertSuccessful()->assertHeader('Access-Control-Allow-Origin',$origin);
  config(['cors.allowed_origins'=>[$origin,'https://second.example']]);
  $this->withHeaders(['Origin'=>'https://unlisted.example'])->options('/api/feedback')->assertSuccessful()->assertHeaderMissing('Access-Control-Allow-Origin');
  $this->withHeaders(['Origin'=>'https://second.example'])->getJson('/api/me')->assertUnauthorized()->assertHeader('Access-Control-Allow-Origin','https://second.example');
 }
 public function test_only_allowlisted_proxy_changes_client_ip_and_https_detection():void {
  config(['security.trusted_proxies'=>['192.0.2.10']]);
  foreach(['192.0.2.10'=>true,'192.0.2.11'=>false] as $remote=>$trusted){
   $request=Request::create('http://portal.example/up','GET',[],[],[],['REMOTE_ADDR'=>$remote,'HTTP_X_FORWARDED_FOR'=>'198.51.100.42','HTTP_X_FORWARDED_PROTO'=>'https','HTTP_X_FORWARDED_PORT'=>'443']);
   $response=app(ConfiguredTrustProxies::class)->handle($request,fn($r)=>response()->json(['ip'=>$r->ip(),'secure'=>$r->isSecure()]));
   $data=$response->getData(true);$this->assertSame($trusted?'198.51.100.42':$remote,$data['ip']);$this->assertSame($trusted,$data['secure']);
  }
  foreach(['*','0.0.0.0/0','::/0','REMOTE_ADDR','invalid'] as $proxy)$this->assertFalse(ProductionConfiguration::validProxy($proxy));
  $this->assertTrue(ProductionConfiguration::validProxy('192.0.2.0/24'));
  $this->assertTrue(ProductionConfiguration::validProxy('2001:db8::/64'));
  $this->app->instance('env','production');
  $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.10'])->withHeaders(['X-Forwarded-Proto'=>'https','X-Forwarded-For'=>'198.51.100.42'])->getJson('http://localhost/up')->assertHeader('Strict-Transport-Security','max-age=31536000');
  $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.11'])->getJson('http://localhost/up')->assertHeaderMissing('Strict-Transport-Security');
 }
 public function test_read_budget_is_per_account_and_does_not_consume_mutation_budget():void {
  config(['security.reads_per_minute'=>1]);$user=User::where('role','requester')->firstOrFail();
  $this->actingAs($user)->getJson('/api/me')->assertOk();$this->getJson('/api/me')->assertTooManyRequests();
  $notice=DB::table('portal_notifications')->where('user_id',$user->id)->first();
  $this->postJson('/api/notifications/'.$notice->id.'/read')->assertOk();
  $other=User::where('role','requester')->where('id','!=',$user->id)->firstOrFail();$this->actingAs($other)->getJson('/api/me')->assertOk();
 }
 public function test_account_limit_is_hashed_scoped_and_recovers_after_cooldown():void {
  config(['security.login_account_per_minute'=>2]);$email='andrea@clsu.demo';
  for($i=0;$i<2;$i++)$this->postJson('/api/login',['email'=>$email,'password'=>'synthetic incorrect password'])->assertUnprocessable();
  $this->postJson('/api/login',['email'=>$email,'password'=>'synthetic incorrect password'])->assertTooManyRequests();
  $this->postJson('/api/login',['email'=>'other-missing@clsu.demo','password'=>'synthetic incorrect password'])->assertUnprocessable();
  $limits=(RateLimiter::limiter('portal-login'))(Request::create('/api/login','POST',['email'=>strtoupper($email)]));
  $this->assertStringNotContainsString($email,$limits[1]->key);$this->assertStringContainsString(hash('sha256',$email),$limits[1]->key);
  $this->travel(61)->seconds();
  $this->postJson('/api/login',['email'=>$email,'password'=>'DemoPass!2026'])->assertOk();
 }
 public function test_in_app_notification_rows_roll_back_with_the_workflow():void {
  $r=ServiceRequest::where('status','Pending')->firstOrFail();$actor=User::where('role','admin')->firstOrFail();
  $before=DB::table('portal_notifications')->count();$events=DB::table('request_events')->count();
  try{DB::transaction(function()use($r,$actor){app(RequestWorkflow::class)->update($actor,$r->id,['action'=>'clarify','message'=>'Please identify the room.','expected_version'=>$r->record_version]);throw new \RuntimeException('intentional outer rollback');});}
  catch(\RuntimeException $error){$this->assertSame('intentional outer rollback',$error->getMessage());}
  $this->assertDatabaseCount('portal_notifications',$before);$this->assertDatabaseCount('request_events',$events);$this->assertSame($r->record_version,$r->fresh()->record_version);
  app(RequestWorkflow::class)->update($actor,$r->id,['action'=>'clarify','message'=>'Please identify the room.','expected_version'=>$r->record_version]);
  $this->assertDatabaseCount('portal_notifications',$before+1);
 }
}
