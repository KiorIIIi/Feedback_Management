<?php
namespace App\Http\Controllers;
use App\Jobs\GenerateReport;
use App\Services\RequestAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class ReportController {
 public function store(Request $request,RequestAccess $access){$access->admin($request->user());$filters=$request->validate(['days'=>'required|integer|min:1|max:366']);$filters['handling_office_id']=$request->user()->office_id;$id=DB::transaction(function()use($request,$filters){\App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();abort_if(DB::table('report_jobs')->where('requested_by',$request->user()->id)->whereIn('status',['pending','processing'])->count()>=config('security.active_reports_per_user'),429,'Your export limit is reached. Wait for an active report to finish.');$id=DB::table('report_jobs')->insertGetId(['requested_by'=>$request->user()->id,'filters'=>json_encode($filters),'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);GenerateReport::dispatch($id)->afterCommit();return $id;});return response()->json(['id'=>$id,'status'=>'pending'],202);}
 private function authorizedJob(Request $request,int $id){$job=DB::table('report_jobs')->where('id',$id)->where('requested_by',$request->user()->id)->first();abort_unless($job,404);$office=json_decode($job->filters,true)['handling_office_id']??null;abort_unless($request->user()->role==='admin'&&$request->user()->is_active&&(int)$office===(int)$request->user()->office_id,403);return $job;}
 public function show(Request $request,int $id){$job=$this->authorizedJob($request,$id);return ['id'=>$id,'status'=>$job->status,'error'=>$job->error,'download_url'=>$job->status==='completed'?'/api/reports/'.$id.'/download':null];}
 public function download(Request $request,int $id){$job=$this->authorizedJob($request,$id);abort_unless($job->status==='completed'&&$job->path&&Storage::disk('local')->exists($job->path),404,'This report file is no longer available. Request a new export.');return Storage::disk('local')->download($job->path,'clsu-service-report-'.$id.'.csv');}
}
