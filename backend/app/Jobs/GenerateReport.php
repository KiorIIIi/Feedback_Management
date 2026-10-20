<?php
namespace App\Jobs;
use App\Models\User;
use App\Models\ServiceRequest;
use App\Services\RequestAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class GenerateReport implements ShouldQueue {
 use Queueable;
 public int $tries=3;
 private function csvCell(mixed $value):string {
  $text=(string)$value;
  return preg_match('/^(?:[\s\p{Z}\p{Cc}\p{Cf}\p{Mn}]*[=+@|\-]|[\t\r\n])/u',$text)?"'".$text:$text;
 }
 public function __construct(public int $reportId){}
 public function handle(RequestAccess $access):void {
  $job=DB::table('report_jobs')->find($this->reportId);if(!$job||$job->status==='completed')return;
  DB::table('report_jobs')->where('id',$job->id)->update(['status'=>'processing','updated_at'=>now()]);
  $user=User::findOrFail($job->requested_by);$access->admin($user);$filters=json_decode($job->filters,true);$days=$filters['days'];
  abort_unless($user->is_active&&(int)$user->office_id===(int)($filters['handling_office_id']??null),403,'Report office authorization changed.');
  $stream=fopen('php://temp','r+');fputcsv($stream,['Control number','Subject','Status','Priority','Submitted','Completed'],',','"','');
  $access->scope(ServiceRequest::query(),$user)->where('created_at','>=',now()->startOfDay()->subDays($days-1))->orderBy('id')->chunkById(200,function($rows)use($stream){foreach($rows as $r){$cells=array_map(fn($value)=>$this->csvCell($value),[$r->job_number,$r->subject,$r->status,$r->priority,$r->created_at,$r->completed_at]);fputcsv($stream,$cells,',','"','');}});
  rewind($stream);$path='reports/'.$job->id.'.csv';Storage::disk('local')->put($path,stream_get_contents($stream));fclose($stream);
  DB::table('report_jobs')->where('id',$job->id)->update(['status'=>'completed','path'=>$path,'error'=>null,'updated_at'=>now()]);
 }
 public function failed(?\Throwable $exception):void {DB::table('report_jobs')->where('id',$this->reportId)->update(['status'=>'failed','error'=>'The export failed. Please request a new report.','updated_at'=>now()]);}
}
