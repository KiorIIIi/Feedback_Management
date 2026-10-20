<?php
namespace App\Jobs;
use App\Models\User;
use App\Services\ReportRenderer;
use App\Services\ServiceEvaluationReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class GenerateReport implements ShouldQueue {
    use Queueable;
    public int $tries=3;
    public function __construct(public int $reportId) {}
    public function handle(ServiceEvaluationReport $reports, ReportRenderer $renderer): void {
        $job=DB::table('report_jobs')->find($this->reportId);
        if (!$job || $job->status==='completed') return;
        DB::table('report_jobs')->where('id',$job->id)->update(['status'=>'processing','updated_at'=>now()]);
        $user=User::findOrFail($job->requested_by);
        $filters=json_decode($job->filters,true,flags:JSON_THROW_ON_ERROR);
        abort_unless($user->is_active && $user->role==='admin' && (int)$user->office_id===(int)($filters['handling_office_id']??null),403,'Report authorization changed.');
        $format=$filters['format']??'csv';
        $snapshot=$reports->build($user,$filters);
        $path='reports/'.$job->id.'.'.$format;
        if (!Storage::disk('local')->put($path,$renderer->render($snapshot,$format))) {
            throw new \RuntimeException('Unable to store the report export.');
        }
        DB::table('report_jobs')->where('id',$job->id)->update(['status'=>'completed','path'=>$path,'error'=>null,'updated_at'=>now()]);
    }
    public function failed(?\Throwable $exception): void {
        DB::table('report_jobs')->where('id',$this->reportId)->update(['status'=>'failed','error'=>'The export failed. Please request a new report.','updated_at'=>now()]);
    }
}
