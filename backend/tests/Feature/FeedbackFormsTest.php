<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedbackFormsTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();}
    private function user(string $role): User {return User::where('role',$role)->firstOrFail();}

    public function test_admin_creates_lists_and_updates_event_forms(): void {
        $admin=$this->user('admin');
        $form=$this->actingAs($admin)->postJson('/api/feedback-forms',[
            'title'=>'University Week 2026',
            'description'=>'Share your experience at the University Week booths.',
            'opens_at'=>now()->subDay()->toDateTimeString(),
            'closes_at'=>now()->addDays(7)->toDateTimeString(),
        ])->assertCreated()->json();
        $this->assertSame('University Week 2026',$form['title']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/',$form['token']);
        $list=$this->actingAs($admin)->getJson('/api/feedback-forms')->assertOk()->json('forms');
        $this->assertSame('open',$list[0]['status']);
        $this->assertSame(0,$list[0]['responses']);
        $this->actingAs($admin)->patchJson('/api/feedback-forms/'.$form['id'],['is_active'=>false])->assertOk();
        $list=$this->actingAs($admin)->getJson('/api/feedback-forms')->assertOk()->json('forms');
        $this->assertSame('closed',$list[0]['status']);
        $this->assertDatabaseHas('audit_entries',['action'=>'feedback_form.created']);
        $this->assertDatabaseHas('audit_entries',['action'=>'feedback_form.updated']);
        // Window consistency is enforced on create and update.
        $this->actingAs($admin)->postJson('/api/feedback-forms',[
            'title'=>'Bad window','opens_at'=>now()->addDay()->toDateTimeString(),'closes_at'=>now()->subDay()->toDateTimeString(),
        ])->assertStatus(422);
        // Only administrators manage forms.
        $this->actingAs($this->user('requester'))->postJson('/api/feedback-forms',['title'=>'Nope'])->assertForbidden();
        $this->actingAs($this->user('personnel'))->getJson('/api/feedback-forms')->assertForbidden();
    }

    public function test_public_metadata_is_scoped_and_unknown_tokens_are_not_found(): void {
        $admin=$this->user('admin');
        $form=$this->actingAs($admin)->postJson('/api/feedback-forms',['title'=>'Alumni Homecoming'])->assertCreated()->json();
        $public=$this->getJson('/api/feedback-forms/'.$form['token'])->assertOk()->json();
        $this->assertSame('Alumni Homecoming',$public['title']);
        $this->assertSame('open',$public['status']);
        $this->assertArrayNotHasKey('token',$public);
        $this->assertArrayNotHasKey('is_active',$public);
        $this->getJson('/api/feedback-forms/deadbeefdeadbeefdeadbeefdeadbeef')->assertNotFound();
    }

    public function test_multiple_forms_accept_simultaneously_within_their_windows(): void {
        $admin=$this->user('admin');
        $a=$this->actingAs($admin)->postJson('/api/feedback-forms',[
            'title'=>'University Week 2026','opens_at'=>now()->subHour()->toDateTimeString(),'closes_at'=>now()->addDays(2)->toDateTimeString(),
        ])->assertCreated()->json();
        $b=$this->actingAs($admin)->postJson('/api/feedback-forms',[
            'title'=>'Alumni Homecoming','opens_at'=>now()->subHour()->toDateTimeString(),'closes_at'=>now()->addDays(5)->toDateTimeString(),
        ])->assertCreated()->json();
        foreach ([$a,$b] as $form) {
            $this->postJson('/api/feedback',[
                'category'=>'Suggestion','subject'=>'Synthetic response for '.$form['title'],
                'message'=>'A synthetic response submitted through an event form link.',
                'feedback_form_id'=>$form['id'],
            ])->assertCreated();
        }
        $list=$this->actingAs($admin)->getJson('/api/feedback-forms')->assertOk()->json('forms');
        $this->assertSame($b['id'],$list[0]['id']);
        $this->assertSame(1,$list[0]['responses']);
        $this->assertSame(1,$list[1]['responses']);
        $newest=DB::table('general_feedback')->orderByDesc('id')->first();
        $this->assertSame($b['id'],$newest->feedback_form_id);
        $detail=$this->actingAs($admin)->getJson('/api/feedback/general/'.$newest->id)->assertOk()->json('record');
        $this->assertSame('Alumni Homecoming',$detail['form_title']);
        $audit=DB::table('audit_entries')->where('action','feedback.submitted')->orderByDesc('id')->first();
        $this->assertSame(['general_feedback_id'=>$newest->id,'feedback_form_id'=>$b['id']],json_decode($audit->after,true));
    }

    public function test_forms_refuse_submissions_outside_their_window(): void {
        $admin=$this->user('admin');
        $base=['category'=>'Other','subject'=>'Window probe','message'=>'A synthetic window probe submission.'];
        $scheduled=$this->actingAs($admin)->postJson('/api/feedback-forms',[
            'title'=>'Future event','opens_at'=>now()->addDay()->toDateTimeString(),'closes_at'=>now()->addDays(2)->toDateTimeString(),
        ])->assertCreated()->json();
        $this->postJson('/api/feedback',$base+['feedback_form_id'=>$scheduled['id']])->assertStatus(422);
        $expired=$this->actingAs($admin)->postJson('/api/feedback-forms',[
            'title'=>'Past event','opens_at'=>now()->subDays(3)->toDateTimeString(),'closes_at'=>now()->subDay()->toDateTimeString(),
        ])->assertCreated()->json();
        $this->postJson('/api/feedback',$base+['feedback_form_id'=>$expired['id']])->assertStatus(422);
        // A live form accepts, then refuses once the administrator closes it.
        $live=$this->actingAs($admin)->postJson('/api/feedback-forms',['title'=>'Live event'])->assertCreated()->json();
        $this->postJson('/api/feedback',$base+['feedback_form_id'=>$live['id']])->assertCreated();
        $this->actingAs($admin)->patchJson('/api/feedback-forms/'.$live['id'],['is_active'=>false])->assertOk();
        $this->postJson('/api/feedback',$base+['feedback_form_id'=>$live['id']])->assertStatus(422);
        $this->assertSame(1,DB::table('general_feedback')->count());
    }
}
