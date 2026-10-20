<?php
namespace App\Http\Controllers;
use App\Jobs\GenerateReport;
use App\Services\RequestAccess;
use App\Services\ServiceEvaluationReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class ReportController {
    private function filters(Request $request): array {
        return $request->validate([
            'days'=>[\Illuminate\Validation\Rule::prohibitedIf($request->has('month')),'required_without:month','integer','min:1','max:366'],
            'month'=>'sometimes|date_format:Y-m', 'format'=>'sometimes|in:csv,pdf,xlsx',
        ]);
    }
    public function summary(Request $request, RequestAccess $access, ServiceEvaluationReport $reports): array {
        $access->admin($request->user());
        $summary=$reports->build($request->user(),$this->filters($request));
        unset($summary['requests']);
        return $summary;
    }
    public function store(Request $request, RequestAccess $access) {
        $access->admin($request->user());
        $filters=$this->filters($request);
        $filters['format']??='csv';
        $filters['handling_office_id']=$request->user()->office_id;
        $id=DB::transaction(function () use ($request,$filters) {
            \App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('report_jobs')->where('requested_by',$request->user()->id)->whereIn('status',['pending','processing'])->count()>=config('security.active_reports_per_user'),429,'Your export limit is reached. Wait for an active report to finish.');
            $id=DB::table('report_jobs')->insertGetId(['requested_by'=>$request->user()->id,'filters'=>json_encode($filters),'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
            GenerateReport::dispatch($id)->afterCommit();
            return $id;
        });
        return response()->json(['id'=>$id,'status'=>'pending','format'=>$filters['format']],202);
    }
    private function authorizedJob(Request $request,int $id): object {
        $job=DB::table('report_jobs')->where('id',$id)->where('requested_by',$request->user()->id)->first();
        abort_unless($job,404);
        $office=json_decode($job->filters,true)['handling_office_id']??null;
        abort_unless($request->user()->role==='admin' && $request->user()->is_active && (int)$office===(int)$request->user()->office_id,403);
        return $job;
    }
    public function show(Request $request,int $id): array {
        $job=$this->authorizedJob($request,$id);
        $format=json_decode($job->filters,true)['format']??'csv';
        $ready=$job->status==='completed' && $job->path && Storage::disk('local')->exists($job->path);
        return ['id'=>$id,'status'=>$job->status,'format'=>$format,'error'=>$job->status==='completed'&&!$ready?'The export file is no longer available. Generate a new report.':$job->error,'download_url'=>$ready?'/api/reports/'.$id.'/download':null];
    }
    public function download(Request $request,int $id) {
        $job=$this->authorizedJob($request,$id);
        $format=json_decode($job->filters,true)['format']??'csv';
        abort_unless(in_array($format,['csv','pdf','xlsx'],true) && $job->status==='completed' && $job->path==='reports/'.$id.'.'.$format && Storage::disk('local')->exists($job->path),404,'This report file is no longer available. Request a new export.');
        $type=match ($format) {'pdf'=>'application/pdf','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',default=>'text/csv; charset=UTF-8'};
        return Storage::disk('local')->download($job->path,'clsu-service-report-'.$id.'.'.$format,['Content-Type'=>$type,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
