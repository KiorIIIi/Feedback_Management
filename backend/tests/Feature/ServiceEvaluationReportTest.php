<?php
namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ReportRenderer;
use App\Services\ServiceEvaluationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ServiceEvaluationReportTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();
        \Illuminate\Support\Facades\Queue::fake([GenerateReport::class]);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 12:00:00','Asia/Manila'));
        DB::table('service_requests')->update(['created_at'=>'2010-01-01 00:00:00']);
    }
    private function admin(): User {return User::where('role','admin')->firstOrFail();}
    private function request(array $attributes=[]): ServiceRequest {
        $r=ServiceRequest::firstOrFail()->replicate();
        $r->forceFill(['job_number'=>'REPORT-'.\Illuminate\Support\Str::uuid(),'subject'=>'Synthetic report concern','status'=>'Pending','priority'=>null,'completed_at'=>null,'created_at'=>'2026-11-01 00:00:00',...$attributes]);
        $r->save();return $r;
    }
    public function test_monthly_totals_denominators_boundaries_scope_and_final_assignment(): void {
        $admin=$this->admin();$category=DB::table('request_categories')->insertGetId(['handling_office_id'=>$admin->office_id,'name'=>'Synthetic connectivity']);
        $a=$this->request(['subject'=>' Network   interruption ','status'=>'Completed','category_id'=>$category,'priority'=>'High','completed_at'=>'2026-11-01 04:00:00']);
        $b=$this->request(['subject'=>'network interruption','status'=>'Completed','completed_at'=>'2026-11-01 08:00:00']);
        $this->request(['status'=>'Completed','completed_at'=>null]);
        $this->request(['status'=>'Completed','completed_at'=>'2026-10-31 23:00:00']);
        $this->request(['status'=>'Completed','completed_at'=>'2026-11-16 00:00:00']);
        $this->request(['created_at'=>'2026-11-15 12:00:00']);
        $this->request(['created_at'=>'2026-10-31 23:59:59']);
        $this->request(['created_at'=>'2026-12-01 00:00:00']);
        $this->request(['created_at'=>'2026-11-15 12:00:01']);
        $foreign=DB::table('offices')->where('id','!=',$admin->office_id)->value('id');
        $this->request(['handling_office_id'=>$foreign,'subject'=>'Other office confidential subject']);
        foreach ([[$a,5],[$b,3]] as [$r,$rating]) DB::table('service_evaluations')->insert(['request_id'=>$r->id,'rating'=>$rating,'created_at'=>now()]);
        $people=User::where('role','personnel')->take(2)->get();
        foreach ($people as $i=>$p) DB::table('assignments')->insert(['service_request_id'=>$a->id,'personnel_id'=>$p->id,'assigned_by'=>$admin->id,'assigned_at'=>'2026-11-01 01:00:00','ended_at'=>'2026-11-01 04:00:00']);
        $report=app(ServiceEvaluationReport::class)->build($admin,['month'=>'2026-11']);$s=$report['summary'];
        $this->assertSame(6,$s['total']);$this->assertSame(5,$s['completed']);$this->assertSame(1,$s['open']);
        $this->assertEquals(4,$s['average_rating']);$this->assertSame(2,$s['rating_count']);
        $this->assertEquals(6,$s['average_resolution_hours']);$this->assertSame(2,$s['resolution_count']);$this->assertSame(3,$s['resolution_excluded']);
        $this->assertSame(83.3,$s['completion_rate']);$this->assertSame('2026-11-01',$report['period_start']);$this->assertSame('2026-11-30',$report['period_end']);
        $concern=collect($report['recurring_concerns'])->firstWhere('subject',' Network   interruption ');$this->assertSame(2,$concern['count']);
        $final=collect($report['personnel'])->firstWhere('id',$people[1]->id);$this->assertSame(1,$final['total']);
        foreach (['categories','priorities','statuses','personnel'] as $key) $this->assertSame(6,array_sum(array_column($report[$key],'total')));
        $this->actingAs($admin)->getJson('/api/report-summary?month=2026-11')->assertOk()->assertJsonPath('summary.total',6)->assertJsonMissingPath('requests')->assertDontSee('Other office confidential subject')->assertDontSee('Demo internal note');
    }
    public function test_empty_report_and_calendar_day_window_are_defined(): void {
        $r=$this->request(['created_at'=>'2026-11-15 00:00:00']);
        $this->request(['created_at'=>'2026-11-14 23:59:59']);
        $report=app(ServiceEvaluationReport::class)->build($this->admin(),['days'=>1]);$this->assertSame(1,$report['summary']['total']);
        $empty=app(ServiceEvaluationReport::class)->build($this->admin(),['month'=>'2026-09']);
        $this->assertSame(0,$empty['summary']['total']);$this->assertNull($empty['summary']['average_rating']);$this->assertNull($empty['summary']['average_resolution_hours']);
        $this->assertSame([], $empty['categories']);
    }
    public function test_report_filters_and_permissions(): void {
        $this->getJson('/api/report-summary?days=30')->assertUnauthorized();
        $this->actingAs(User::where('role','requester')->firstOrFail())->getJson('/api/report-summary?days=30')->assertForbidden();
        $this->actingAs($this->admin());
        foreach (['','days=0','days=367','month=2026-13','month=2026-1','month=2026-11&days=30'] as $query) $this->getJson('/api/report-summary?'.$query)->assertUnprocessable();
        $this->postJson('/api/reports',['month'=>'2026-11','format'=>'html'])->assertUnprocessable();
    }
    public function test_exports_use_real_formats_protect_text_and_complete_only_after_storage(): void {
        Storage::fake('local');$admin=$this->admin();$r=$this->request(['subject'=>'=HYPERLINK("https://example.invalid","synthetic")']);
        foreach (['pdf','xlsx','csv'] as $format) {
            $id=$this->actingAs($admin)->postJson('/api/reports',['month'=>'2026-11','format'=>$format])->assertAccepted()->json('id');
            $this->getJson('/api/reports/'.$id)->assertJsonPath('status','pending')->assertJsonPath('download_url',null);
            $this->getJson('/api/reports/'.$id.'/download')->assertNotFound();
            app()->call([new GenerateReport($id),'handle']);
            $this->getJson('/api/reports/'.$id)->assertJsonPath('status','completed')->assertJsonPath('format',$format);
            $bytes=Storage::disk('local')->get('reports/'.$id.'.'.$format);
            $this->get('/api/reports/'.$id.'/download')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
            app()->call([new GenerateReport($id),'handle']);$this->assertSame($bytes,Storage::disk('local')->get('reports/'.$id.'.'.$format));
            if ($format==='pdf') $this->assertStringStartsWith('%PDF-',$bytes);
            if ($format==='csv') $this->assertStringContainsString("'=HYPERLINK",$bytes);
            if ($format==='xlsx') {
                $this->assertStringStartsWith('PK',$bytes);
                $book=IOFactory::load(Storage::disk('local')->path('reports/'.$id.'.xlsx'));
                $this->assertSame(['Summary','Categories','Priorities','Statuses','Personnel','Recurring concerns','Requests'],$book->getSheetNames());
                $cell=$book->getSheetByName('Requests')->getCell('B6');$this->assertSame('s',$cell->getDataType());$this->assertSame($r->subject,$cell->getValue());
                $this->assertSame(1,$book->getSheetByName('Summary')->getCell('B6')->getValue());$book->disconnectWorksheets();
            }
            Storage::disk('local')->delete('reports/'.$id.'.'.$format);
            $this->getJson('/api/reports/'.$id)->assertJsonPath('download_url',null);
        }
    }
    public function test_revoked_authorization_and_failed_jobs_never_offer_downloads(): void {
        Storage::fake('local');$admin=$this->admin();
        $id=$this->actingAs($admin)->postJson('/api/reports',['days'=>30,'format'=>'pdf'])->assertAccepted()->json('id');
        $admin->office_id=DB::table('offices')->where('id','!=',$admin->office_id)->value('id');$admin->save();
        try {app()->call([new GenerateReport($id),'handle']);$this->fail('Changed office must revoke queued export access.');}
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {$this->assertSame(403,$e->getStatusCode());}
        (new GenerateReport($id))->failed(new \RuntimeException('Sensitive implementation detail'));
        $this->assertDatabaseHas('report_jobs',['id'=>$id,'status'=>'failed','path'=>null]);Storage::disk('local')->assertMissing('reports/'.$id.'.pdf');
        $this->getJson('/api/reports/'.$id)->assertForbidden();
    }
}
