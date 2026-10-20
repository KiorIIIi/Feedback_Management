<?php
namespace Database\Seeders;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder {
 public function run():void {
  if(!app()->environment(['local','testing'])||!filter_var(env('DEMO_SEED',false),FILTER_VALIDATE_BOOL))throw new \RuntimeException('Demo seeding requires a local/testing environment and DEMO_SEED=true.');
  if(DB::table('offices')->exists())throw new \RuntimeException('The database already has records. Demo seeding only runs once against an empty database.');
  DB::transaction(function(){
   $miso=DB::table('offices')->insertGetId(['office_code'=>'MISO','name'=>'Management Information Systems Office']);
   $offices=[];foreach(['Engineering'=>'College of Engineering','Library'=>'University Library','Science'=>'College of Science','Education'=>'College of Education','Registrar'=>'Office of the Registrar'] as $code=>$name)$offices[$code]=DB::table('offices')->insertGetId(['office_code'=>$code,'name'=>$name]);
   $types=[];foreach(['Wi-Fi/IDF troubleshooting','Equipment maintenance','Technical/event support','Electrical','Plumbing','Other university services'] as $name)$types[]=DB::table('service_types')->insertGetId(['name'=>$name]);
   $make=function($name,$email,$role,$office,$classification='Staff'){$u=new User();$u->forceFill(['name'=>$name,'email'=>$email,'password'=>'DemoPass!2026','role'=>$role,'office_id'=>$office,'classification'=>$classification,'is_active'=>true]);$u->save();DB::table('office_memberships')->insert(['user_id'=>$u->id,'office_id'=>$office]);return $u;};
   $admin=$make('Admin Dela Cruz','admin@clsu.demo','admin',$miso);
   $requester=$make('Andrea Santos','andrea@clsu.demo','requester',$offices['Engineering'],'Faculty');
   $other=$make('Jamie Reyes','jamie@clsu.demo','requester',$offices['Science'],'Student');
   $personnel=[];foreach([['Mark Dela Cruz','mark@clsu.demo'],['John Santos','john@clsu.demo'],['Paul Garcia','paul@clsu.demo'],['Maria Torres','maria@clsu.demo']] as [$name,$email]){$p=$make($name,$email,'personnel',$miso);$personnel[]=$p;foreach($types as $type)DB::table('personnel_eligibilities')->insert(['user_id'=>$p->id,'service_type_id'=>$type]);}
   $v=DB::table('priority_rule_versions')->insertGetId(['version_number'=>'demo-v1','effective_from'=>now()->toDateString(),'is_demo'=>true]);
   foreach(['Low','Medium','High'] as $urgency)foreach(['Individual','One room','Entire office','Campus-wide'] as $impact){$score=array_search($urgency,['Low','Medium','High'])+array_search($impact,['Individual','One room','Entire office','Campus-wide']);$priority=$score>=4?'Critical':($score>=3?'High':($score>=1?'Medium':'Low'));DB::table('priority_rules')->insert(['priority_rule_version_id'=>$v,'urgency'=>$urgency,'impact'=>$impact,'resulting_priority'=>$priority]);}
   $samples=[
    ['Internet connectivity issue in laboratory','Laboratory workstations are unable to maintain a stable internet connection. Online learning activities and access to university services are interrupted. Technical assistance is requested to check and restore connectivity.','Laboratory · College of Engineering',0,'Engineering','In Progress','Critical',0,'High','Entire office'],
    ['Air conditioning unit not cooling','The air conditioning unit in the reading area is not cooling. Please inspect the unit and advise on repairs.','University Library · Reading area',1,'Library','Assigned','High',1,'High','One room'],
    ['Request for software installation','Please install the approved teaching application on the laboratory workstation for the upcoming practical session.','College of Science · Computer room',2,'Science','Pending',null,null,'Medium','One room'],
    ['Leaking faucet in faculty restroom','The faculty restroom faucet continues to leak after it is closed. Please inspect and repair the fixture.','College of Education · Faculty restroom',4,'Education','In Progress','High',2,'Medium','Entire office'],
    ['Account recovery assistance','Unable to access the university account after password reset. Please assist with account recovery.','Office of the Registrar',5,'Registrar','Completed','Low',3,'Low','Individual'],
    ['Event technical support','Please assist with audio and projection equipment for a college event. The specific schedule and equipment are to be confirmed.','College of Engineering · Function hall',2,'Engineering','For Clarification','Medium',null,'Medium','One room'],
    ['Projector maintenance','The classroom projector display is blurry and the air filter needs inspection. Please perform maintenance and test the display.','College of Engineering · Room 203',1,'Engineering','Completed','Medium',3,'Medium','One room'],
   ];
   foreach(array_reverse($samples) as $i=>$s){[$subject,$description,$location,$type,$office,$status,$priority,$assignee,$urgency,$impact]=$s;$owner=$office==='Science'?$other:$requester;$created=now()->subDays(6-$i)->setTime(9,20);
    $rule=DB::table('priority_rules')->where('urgency',$urgency)->where('impact',$impact)->first();
    $r=new ServiceRequest();$r->forceFill(['job_number'=>'FMS-2026-'.str_pad((string)(124+$i),4,'0',STR_PAD_LEFT),'requester_id'=>$owner->id,'requesting_office_id'=>$offices[$office],'handling_office_id'=>$miso,'service_type_id'=>$types[$type],'subject'=>$subject,'description'=>$description,'location'=>$location,'urgency'=>$urgency,'impact'=>$impact,'priority'=>$priority,'priority_rule_id'=>$priority?$rule->id:null,'status'=>$status,'record_version'=>1,'created_at'=>$created,'updated_at'=>$created,'first_response_at'=>$assignee!==null?$created->copy()->addMinutes(20):null,'completed_at'=>$status==='Completed'?$created->copy()->addHours(4):null]);$r->save();
    $workflow=app(RequestWorkflow::class);$workflow->event($r,$owner,'Request submitted',$description);
    if($assignee!==null){DB::table('assignments')->insert(['service_request_id'=>$r->id,'personnel_id'=>$personnel[$assignee]->id,'assigned_by'=>$admin->id,'assigned_at'=>$created->copy()->addMinutes(20),'ended_at'=>$status==='Completed'?$r->completed_at:null]);$workflow->event($r,$admin,'Assigned','Assigned to '.$personnel[$assignee]->name);}
    if($status==='In Progress')$workflow->event($r,$personnel[$assignee],'In Progress','Inspection started. Checking the affected equipment and connections.');
    if($status==='For Clarification')$workflow->event($r,$admin,'Clarification requested','Please provide the event date, venue, expected audience, and equipment requirements.');
    if($status==='Completed'){$workflow->event($r,$personnel[$assignee],'Actions taken','Inspected the unit, performed maintenance, and tested the result with the requesting office.');$workflow->event($r,$personnel[$assignee],'Completed','Service restored and validated with the requester.');}
    $workflow->event($r,$admin,'Internal review','Demo internal note. This must never appear in requester responses.','internal');
    DB::table('portal_notifications')->insert(['user_id'=>$owner->id,'service_request_id'=>$r->id,'message'=>$status==='For Clarification'?'A few more details are needed for your event support request.':'Service update: '.$subject.' — '.$status,'created_at'=>now()->subMinutes($i*10)]);
   }
  });
 }
}
