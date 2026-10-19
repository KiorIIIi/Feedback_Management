<?php
namespace App\Services;
use App\Models\User;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RequestAccess {
 public function scope(Builder $query,User $user): Builder {
  if($user->role==='requester') return $query->where('requester_id',$user->id);
  if($user->role==='admin') return $query->where('handling_office_id',$user->office_id);
  return $query->whereExists(fn($q)=>$q->selectRaw('1')->from('assignments')->whereColumn('service_request_id','service_requests.id')->where('personnel_id',$user->id)->where(fn($s)=>$s->whereNull('ended_at')->orWhere('service_requests.status','Completed')));
 }
 public function authorize(User $user, ServiceRequest $request): void {
  abort_unless($this->scope(ServiceRequest::query(),$user)->whereKey($request->id)->exists(),404);
 }
 public function admin(User $user): void {abort_unless($user->role==='admin',403,'Administrator access required.');}
 public function present(ServiceRequest $request,User $user,bool $detail=false): array {
  $this->authorize($user,$request);
  $data=$request->toArray();
  $assignment=DB::table('assignments')->join('users','users.id','=','assignments.personnel_id')->where('service_request_id',$request->id)->when($request->status!=='Completed',fn($q)=>$q->whereNull('ended_at'))->orderByDesc('assignments.id')->select('users.id','users.name')->first();
  $data['office']=DB::table('offices')->where('id',$request->requesting_office_id)->value('name')??'No office affiliation';
  $data['service_type']=DB::table('service_types')->where('id',$request->service_type_id)->value('name');
  $data['assignee']=$assignment?->name;$data['assignee_id']=$assignment?->id;
  $data['evaluation']=DB::table('service_evaluations')->where('request_id',$request->id)->first();
  if($detail){
   $events=DB::table('request_events')->join('users','users.id','=','request_events.actor_id')->where('service_request_id',$request->id);
   if($user->role==='requester')$events->where('visibility','requester');
   $data['events']=$events->orderByDesc('request_events.id')->select('request_events.*','users.name as actor')->get();
  }
  return $data;
 }
}
