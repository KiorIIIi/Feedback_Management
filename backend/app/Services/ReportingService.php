<?php
namespace App\Services;
use App\Models\User;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
class ReportingService {
 public function __construct(private RequestAccess $access){}
 public function metrics(User $user,int $days):array {
  $rows=$this->access->scope(ServiceRequest::query(),$user)->where('created_at','>=',now()->startOfDay()->subDays($days-1))->get();
  $ids=$rows->pluck('id');$ratings=DB::table('service_evaluations')->whereIn('request_id',$ids)->get();
  $completed=$rows->where('status','Completed')->count();$total=$rows->count();
  $daily=$rows->groupBy(fn($r)=>$r->created_at->format('Y-m-d'))->sortKeys()->map(fn($g,$day)=>['date'=>$day,'submitted'=>$g->count(),'completed'=>$g->where('status','Completed')->count()])->values();
  return ['total'=>$total,'awaiting'=>$rows->whereIn('status',['Pending','For Clarification','On Hold'])->count(),'completed'=>$completed,'completion_rate'=>$total?round($completed/$total*100,1):0,'rating_count'=>$ratings->count(),'satisfaction'=>$ratings->count()?round($ratings->avg('rating'),1):null,'priority'=>$rows->groupBy(fn($r)=>$r->priority??'Unreviewed')->map->count(),'daily'=>$daily,'days'=>$days,'period_start'=>now()->startOfDay()->subDays($days-1)->toDateString(),'period_end'=>now()->toDateString(),'definition'=>'Requests submitted in this period; completion counts describe their current status. Calendar days, Asia/Manila.'];
 }
}
