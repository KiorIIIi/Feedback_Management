<?php
namespace App\Services;
use App\Models\User;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
class ReportingService {
 public function __construct(private RequestAccess $access){}
 // Aggregates run in the database through the same access scope; the request rows
 // themselves are never materialized (audit finding A13).
 public function metrics(User $user,int $days):array {
  $start=now()->startOfDay()->subDays($days-1);
  $base=fn()=>$this->access->scope(ServiceRequest::query(),$user)->where('created_at','>=',$start);
  $total=$base()->count();
  $completed=$base()->where('status','Completed')->count();
  $awaiting=$base()->whereIn('status',['Pending','For Clarification','On Hold'])->count();
  $priority=$base()->selectRaw("COALESCE(priority,'Unreviewed') as bucket, COUNT(*) as total")
   ->groupBy('bucket')->orderBy('bucket')->pluck('total','bucket');
  $daily=$base()->selectRaw("DATE(created_at) as day, COUNT(*) as submitted, SUM(status='Completed') as completed")
   ->groupBy('day')->orderBy('day')->get()
   ->map(fn($row)=>['date'=>$row->day,'submitted'=>(int)$row->submitted,'completed'=>(int)$row->completed])->values();
  $ids=$base()->pluck('service_requests.id');
  $ratings=$ids->isEmpty()?null:DB::table('service_evaluations')->whereIn('request_id',$ids)
   ->whereBetween('rating',[1,5])->selectRaw('COUNT(*) as count, AVG(rating) as average')->first();
  return ['total'=>$total,'awaiting'=>$awaiting,'completed'=>$completed,'completion_rate'=>$total?round($completed/$total*100,1):0,
   'rating_count'=>(int)($ratings->count??0),'satisfaction'=>($ratings->count??0)?round((float)$ratings->average,1):null,
   'priority'=>$priority,'daily'=>$daily,'days'=>$days,
   'period_start'=>$start->toDateString(),'period_end'=>now()->toDateString(),
   'definition'=>'Requests submitted in this period; completion counts describe their current status. Calendar days, Asia/Manila.'];
 }
}
