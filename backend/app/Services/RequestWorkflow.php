<?php
namespace App\Services;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestWorkflow {
 public function __construct(private RequestAccess $access){}
 private function fail(string $field,string $message):never {throw ValidationException::withMessages([$field=>$message]);}
 public function event(ServiceRequest $r,User $actor,string $kind,string $message,string $visibility='requester',array $metadata=[]):void {
  DB::table('request_events')->insert(['service_request_id'=>$r->id,'actor_id'=>$actor->id,'kind'=>$kind,'message'=>$message,'visibility'=>$visibility,'metadata'=>json_encode($metadata),'created_at'=>now()]);
 }
 private function audit(ServiceRequest $r,User $actor,string $action,?array $before):void {
  DB::table('audit_entries')->insert(['actor_user_id'=>$actor->id,'actor_kind'=>'human','action'=>$action,'service_request_id'=>$r->id,'before'=>$before?json_encode($before):null,'after'=>json_encode($r->toArray()),'occurred_at'=>now()]);
 }
 public function create(User $actor,array $data):ServiceRequest {
  abort_unless(in_array($actor->role,['requester','admin'],true),403);
  if(isset($data['requesting_office_id'])&&!DB::table('office_memberships')->where('user_id',$actor->id)->where('office_id',$data['requesting_office_id'])->exists())$this->fail('requesting_office_id','Choose one of your office affiliations.');
  return DB::transaction(function()use($actor,$data){
   $handling=DB::table('offices')->where('office_code','MISO')->value('id');abort_unless($handling,503,'The handling office has not been configured.');
   $r=ServiceRequest::create([...$data,'requester_id'=>$actor->id,'handling_office_id'=>$handling,'job_number'=>'FMS-'.now()->format('Y').'-'.strtoupper((string)Str::ulid()),'status'=>'Pending','record_version'=>1]);
   $this->event($r,$actor,'Request submitted',$r->description);$this->audit($r,$actor,'request.created',null);
   $this->notify($r,'Your request has been received.');return $r->refresh();
  });
 }
 private function notify(ServiceRequest $r,string $message):void {
  DB::table('portal_notifications')->insert(['user_id'=>$r->requester_id,'service_request_id'=>$r->id,'message'=>$message,'created_at'=>now()]);
 }
 public function update(User $actor,int $id,array $data):ServiceRequest {
  return DB::transaction(function()use($actor,$id,$data){
   $r=ServiceRequest::lockForUpdate()->findOrFail($id);$this->access->authorize($actor,$r);
   abort_if($r->record_version!==(int)$data['expected_version'],409,'This request changed in another session. Refresh it before saving.');
   $before=$r->toArray();$action=$data['action'];$message=trim($data['message']??'');
   if($action==='reply'){
    abort_unless($actor->id===$r->requester_id&&$r->status==='For Clarification',403,'Only the requester can reply to an open clarification.');
    if(!$message)$this->fail('message','Enter the clarification details.');
    DB::table('request_revisions')->insert(['service_request_id'=>$r->id,'author_id'=>$actor->id,'snapshot'=>json_encode(['original'=>$before,'clarification_response'=>$message]),'created_at'=>now()]);
    $this->event($r,$actor,'Clarification response',$message);$r->status='Pending';
   }elseif($action==='clarify'){
    $this->access->admin($actor);if(!$message)$this->fail('message','Specify the details the requester needs to provide.');
    if(!in_array('For Clarification',config('workflow.transitions.'.$r->status,[]),true))$this->fail('status','Clarification is not available in the current status.');
    $this->event($r,$actor,'Clarification requested',$message);$r->status='For Clarification';$r->first_response_at??=now();
   }elseif($action==='manage'){
    abort_if($actor->role==='requester',403);
    if($r->status==='Completed')$this->fail('status','Completed requests cannot be edited.');
    $current=DB::table('assignments')->where('service_request_id',$r->id)->whereNull('ended_at')->first();
    $personnel=isset($data['personnel_id'])?(int)$data['personnel_id']:($current?->personnel_id);
    if($personnel!==($current?->personnel_id)){
     $this->access->admin($actor);
     $eligible=User::whereKey($personnel)->where('role','personnel')->where('is_active',true)->where('office_id',$r->handling_office_id)->exists()&&DB::table('personnel_eligibilities')->where('user_id',$personnel)->where('service_type_id',$r->service_type_id)->exists();
     if(!$eligible)$this->fail('personnel_id','Select active personnel eligible for this service and handling office.');
     DB::table('assignments')->where('service_request_id',$r->id)->whereNull('ended_at')->update(['ended_at'=>now()]);
     DB::table('assignments')->insert(['service_request_id'=>$r->id,'personnel_id'=>$personnel,'assigned_by'=>$actor->id,'assigned_at'=>now()]);
     $this->event($r,$actor,'Personnel assigned','Assigned to '.User::findOrFail($personnel)->name);$r->first_response_at??=now();
    }
    if(array_key_exists('priority',$data)&&$data['priority']!==$r->priority){
     $this->access->admin($actor);
     $rule=DB::table('priority_rules')->join('priority_rule_versions','priority_rule_versions.id','=','priority_rules.priority_rule_version_id')->where('urgency',$r->urgency)->where('impact',$r->impact)->orderByDesc('priority_rule_version_id')->select('priority_rules.*')->first();
     if(!$rule)$this->fail('priority','No priority rule is configured for this situation.');
     if($data['priority']!==$rule->resulting_priority&&!trim($data['reason']??''))$this->fail('reason','A reason is required when overriding the priority matrix.');
     $r->priority=$data['priority'];$r->priority_rule_id=$rule->id;
     $this->event($r,$actor,'Priority reviewed','Official priority: '.$r->priority.'. '.($data['reason']??'Draft matrix applied.'),'internal');
    }
    $next=$data['status']??$r->status;
    if($next!==$r->status){
     if(!in_array($next,config('workflow.transitions.'.$r->status,[]),true))$this->fail('status','This status transition is not allowed.');
     if(in_array($next,['Assigned','In Progress','Completed'],true)&&!$personnel)$this->fail('personnel_id','Assign eligible personnel first.');
     if(in_array($next,['For Clarification'],true))$this->fail('status','Use Request clarification to record a message for the requester.');
     if($r->status==='For Clarification'&&$next==='Pending')$this->fail('status','The requester must respond to the clarification before review resumes.');
     if(in_array($next,['In Progress','On Hold','Completed'],true)&&!$message)$this->fail('message','Record the actions taken or the reason for this status change.');
     if($next==='Completed'&&!trim($data['outcome']??''))$this->fail('outcome','Record the service outcome before completing the job.');
     $r->status=$next;if($next==='Completed'){$r->completed_at=now();DB::table('assignments')->where('service_request_id',$r->id)->whereNull('ended_at')->update(['ended_at'=>now()]);}
     $this->event($r,$actor,$next,$message?:'Request '.$next);
    }
    if($message)$this->event($r,$actor,$r->status==='Completed'?'Actions taken':'Progress update',$message,$data['visibility']??'requester');
    if($r->status==='Completed')$this->event($r,$actor,'Service outcome',trim($data['outcome']));
    $r->first_response_at??=now();
   }else $this->fail('action','Unknown workflow action.');
   $r->record_version++;$r->save();$this->audit($r,$actor,'request.'.$action,$before);$this->notify($r,'Your request has an update: '.$r->status.'.');return $r;
  });
 }
 public function evaluate(User $actor,int $id,array $data):void {
  DB::transaction(function()use($actor,$id,$data){
   $r=ServiceRequest::lockForUpdate()->findOrFail($id);abort_unless($r->requester_id===$actor->id,404);
   if($r->status!=='Completed')$this->fail('rating','Only completed services can be evaluated.');
   if(DB::table('service_evaluations')->where('request_id',$id)->exists())$this->fail('rating','You already evaluated this service.');
   DB::table('service_evaluations')->insert(['request_id'=>$id,...$data,'created_at'=>now()]);
   $this->audit($r,$actor,'request.evaluated',null);
  });
 }
}
