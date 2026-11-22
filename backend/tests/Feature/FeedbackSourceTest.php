<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedbackSourceTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();}
    private function user(string $role): User {return User::where('role',$role)->firstOrFail();}

    public function test_public_form_link_label_is_stored_and_visible_to_miso(): void {
        $this->postJson('/api/feedback',[
            'category'=>'Suggestion','subject'=>'Cleaner venue signage',
            'message'=>'Signage at the event venue was confusing for first-time visitors. Synthetic feedback.',
            'source_label'=>'University Week 2026',
        ])->assertCreated();
        $id=DB::table('general_feedback')->max('id');
        $this->assertNotNull($id);
        $this->assertSame('University Week 2026',DB::table('general_feedback')->where('id',$id)->value('source_label'));
        $this->actingAs($this->user('admin'))->getJson("/api/feedback/general/$id")->assertOk()
            ->assertJsonPath('record.source_label','University Week 2026');
    }

    public function test_feedback_without_a_link_label_stores_null(): void {
        $this->postJson('/api/feedback',[
            'category'=>'Other','subject'=>'No label reference',
            'message'=>'A synthetic submission made without a shared form label.',
        ])->assertCreated();
        $this->assertNull(DB::table('general_feedback')->orderByDesc('id')->first()->source_label);
    }

    public function test_link_label_is_validated(): void {
        $base=['category'=>'Other','subject'=>'Label validation','message'=>'A synthetic submission for label validation.'];
        $this->postJson('/api/feedback',$base+['source_label'=>str_repeat('x',121)])
            ->assertStatus(422)->assertJsonValidationErrors('source_label');
        $this->postJson('/api/feedback',$base+['source_label'=>['not a string']])
            ->assertStatus(422)->assertJsonValidationErrors('source_label');
        $this->postJson('/api/feedback',$base+['source_label'=>str_repeat('x',120)])->assertCreated();
    }

    public function test_authenticated_owner_sees_their_own_link_label(): void {
        $owner=$this->user('requester');
        $this->actingAs($owner)->postJson('/api/feedback',[
            'category'=>'Compliment','subject'=>'Great event support',
            'message'=>'The support desk at the orientation event was very helpful. Synthetic feedback.',
            'source_label'=>'Freshman Orientation',
        ])->assertCreated();
        $id=DB::table('general_feedback')->max('id');
        $this->actingAs($owner)->getJson("/api/feedback/general/$id")->assertOk()
            ->assertJsonPath('record.source_label','Freshman Orientation');
        $other=User::where('role','requester')->where('id','!=',$owner->id)->firstOrFail();
        $this->actingAs($other)->getJson("/api/feedback/general/$id")->assertNotFound();
    }
}
