<?php
namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalSecurityReviewTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();}

 public function test_mass_assignment_cannot_change_user_authority():void {
  $user=User::where('role','requester')->firstOrFail();$before=$user->only(['id','role','office_id','is_active']);
  $user->fill(['id'=>999999,'role'=>'admin','office_id'=>999999,'is_active'=>false,'remember_token'=>'untrusted','name'=>'Updated display name']);
  $this->assertSame($before,$user->only(['id','role','office_id','is_active']));
  $this->assertNull($user->remember_token);
  $this->assertSame('Updated display name',$user->name);
 }
 public function test_mass_assignment_cannot_change_request_identity_or_workflow():void {
  $request=ServiceRequest::where('status','Pending')->firstOrFail();
  $fields=['id','job_number','requester_id','handling_office_id','status','priority','record_version','completed_at'];$before=$request->only($fields);
  $request->fill(['id'=>999999,'job_number'=>'UNTRUSTED','requester_id'=>999999,'handling_office_id'=>999999,'status'=>'Completed','priority'=>'Critical','record_version'=>999,'completed_at'=>now(),'subject'=>'Updated subject']);
  $this->assertEquals($before,$request->only($fields));
  $this->assertSame('Updated subject',$request->subject);
 }
 public function test_security_headers_are_set_on_success_and_authentication_errors():void {
  foreach(['/up','/api/me'] as $path)$this->getJson($path)
   ->assertHeader('X-Content-Type-Options','nosniff')
   ->assertHeader('X-Frame-Options','DENY')
   ->assertHeader('Referrer-Policy','strict-origin-when-cross-origin');
 }
 public function test_production_headers_enable_csp_and_only_https_gets_hsts():void {
  $this->app->instance('env','production');
  $this->getJson('https://localhost/up')->assertHeader('Strict-Transport-Security','max-age=31536000')->assertHeader('Content-Security-Policy');
  $this->getJson('http://localhost/up')->assertHeaderMissing('Strict-Transport-Security');
 }
 public function test_failed_login_paths_do_not_have_the_reported_instant_return():void {
  $user=User::where('role','requester')->firstOrFail();
  $cases=[['email'=>'missing@clsu.demo'],['email'=>$user->email],['email'=>$user->email,'role'=>'admin']];
  foreach($cases as $case){
   $start=hrtime(true);
   $this->postJson('/api/login',[...$case,'password'=>'synthetic incorrect password'])->assertUnprocessable();
   $this->assertGreaterThanOrEqual(0.18,(hrtime(true)-$start)/1e9,'Failed authentication should retain Laravel timebox mitigation.');
  }
 }
}
