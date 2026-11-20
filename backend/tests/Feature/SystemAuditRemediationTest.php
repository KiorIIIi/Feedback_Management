<?php
namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\RequestAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemAuditRemediationTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();}
    private function user(string $role): User {return User::where('role',$role)->firstOrFail();}

    public function test_malformed_login_email_is_rejected_and_throttled_without_server_errors(): void {
        $payload=['email'=>['attacker@example.invalid'],'password'=>'x'];
        $statuses=[];
        for ($i=0;$i<14;$i++) $statuses[]=$this->postJson('/api/login',$payload)->status();
        // No unhandled exception (A1): validation is reached and throttling engages.
        $this->assertNotContains(500,$statuses);
        $this->assertSame(422,$statuses[0]);
        $this->assertContains(429,$statuses);
        $this->assertSame(429,end($statuses));
    }

    public function test_a_late_report_failure_cannot_downgrade_a_completed_export(): void {
        $admin=$this->user('admin');
        $id=DB::table('report_jobs')->insertGetId([
            'requested_by'=>$admin->id,'filters'=>json_encode(['handling_office_id'=>$admin->office_id,'format'=>'csv']),
            'status'=>'processing','created_at'=>now(),'updated_at'=>now(),
        ]);
        (new GenerateReport($id))->failed(new \RuntimeException('Synthetic attempt failure'));
        $this->assertSame('failed',DB::table('report_jobs')->where('id',$id)->value('status'));
        DB::table('report_jobs')->where('id',$id)->update(['status'=>'completed','path'=>"reports/$id.csv",'error'=>null]);
        (new GenerateReport($id))->failed(new \RuntimeException('Synthetic late failure'));
        $row=DB::table('report_jobs')->where('id',$id)->first();
        $this->assertSame('completed',$row->status);
        $this->assertSame("reports/$id.csv",$row->path);
    }

    public function test_feedback_submission_audit_identifies_the_record(): void {
        $this->postJson('/api/feedback',[
            'category'=>'Suggestion','subject'=>'Audit attribution probe',
            'message'=>'A synthetic submission used to verify audit record attribution.',
            'source_label'=>'Audit probe',
        ])->assertCreated();
        $id=DB::table('general_feedback')->max('id');
        $audit=DB::table('audit_entries')->where('action','feedback.submitted')->orderByDesc('id')->first();
        $this->assertNotNull($audit);
        // Additive audit fields (e.g. a feedback form id) must not break attribution.
        $after=json_decode($audit->after,true);
        $this->assertSame($id,$after['general_feedback_id'] ?? null);
        $this->assertArrayNotHasKey('message',$after);
        $this->assertArrayNotHasKey('contact_email',$after);
    }

    /** Row-based reference aggregation compared against the SQL-aggregate implementation. */
    private function expectedMetrics(User $user,int $days): array {
        $start=now()->startOfDay()->subDays($days-1);
        $rows=app(RequestAccess::class)->scope(ServiceRequest::query(),$user)->where('created_at','>=',$start)->get();
        $ratings=DB::table('service_evaluations')->whereIn('request_id',$rows->pluck('id'))->whereBetween('rating',[1,5])->get();
        return [
            'total'=>$rows->count(),
            'awaiting'=>$rows->whereIn('status',['Pending','For Clarification','On Hold'])->count(),
            'completed'=>$rows->where('status','Completed')->count(),
            'rating_count'=>$ratings->count(),
            'priority'=>$rows->groupBy(fn($r)=>$r->priority??'Unreviewed')->map->count()->all(),
            'daily'=>$rows->groupBy(fn($r)=>$r->created_at->format('Y-m-d'))->sortKeys()
                ->map(fn($g,$day)=>['date'=>$day,'submitted'=>$g->count(),'completed'=>$g->where('status','Completed')->count()])->values()->all(),
        ];
    }

    public function test_dashboard_metrics_match_row_based_reference_for_every_role(): void {
        foreach (['admin','personnel','requester'] as $role) {
            $user=$this->user($role);
            $expected=$this->expectedMetrics($user,366);
            $metrics=$this->actingAs($user)->getJson('/api/metrics?days=366')->assertOk()->json();
            $this->assertSame($expected['total'],$metrics['total'],"total for $role");
            $this->assertSame($expected['awaiting'],$metrics['awaiting'],"awaiting for $role");
            $this->assertSame($expected['completed'],$metrics['completed'],"completed for $role");
            $this->assertEquals($expected['total']?round($expected['completed']/$expected['total']*100,1):0,$metrics['completion_rate'],"completion_rate for $role");
            $this->assertSame($expected['rating_count'],$metrics['rating_count'],"rating_count for $role");
            $this->assertEquals($expected['priority'],$metrics['priority'],"priority buckets for $role");
            $this->assertEquals($expected['daily'],$metrics['daily'],"daily buckets for $role");
            $this->assertSame(366,$metrics['days']);
        }
    }
}
