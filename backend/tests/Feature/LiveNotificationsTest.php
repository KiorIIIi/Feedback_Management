<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\GeneralFeedback;
use App\Services\PortalNotifications;
use App\Services\RequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); config(['hashing.bcrypt.rounds' => 4]); $this->seed(); }
    private function user(string $role): User { return User::where('role', $role)->firstOrFail(); }
    private function payload(): array
    {
        return ['service_type_id' => DB::table('service_types')->value('id'), 'subject' => 'Synthetic live service request',
            'description' => 'Synthetic service request for live notification verification.', 'location' => 'Synthetic laboratory', 'urgency' => 'Low', 'impact' => 'Individual'];
    }

    public function test_new_request_alerts_only_active_handling_office_administrators_and_owner(): void
    {
        $admin = $this->user('admin');
        $foreign = $admin->replicate(); $foreign->email = 'foreign-admin@example.invalid';
        $foreign->office_id = DB::table('offices')->where('office_code', 'Science')->value('id'); $foreign->save();
        $inactive = $admin->replicate(); $inactive->email = 'inactive-admin@example.invalid'; $inactive->is_active = false; $inactive->save();
        $owner = $this->user('requester');
        $created = $this->actingAs($owner)->postJson('/api/requests', $this->payload())->assertCreated()->json();
        $this->assertEqualsCanonicalizing([$owner->id, $admin->id], DB::table('portal_notifications')->where('service_request_id', $created['id'])->pluck('user_id')->all());
        $this->actingAs($admin)->getJson('/api/notifications?feed=1')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('unread_count', 1)->assertJsonPath('notifications.0.url', '/app/requests/'.$created['id'])
            ->assertJsonPath('notifications.0.message', 'New service request received.');
        $this->actingAs($foreign)->getJson('/api/notifications?feed=1')->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'notifications');
    }

    public function test_general_feedback_alert_has_typed_target_and_is_reauthorized_on_read_and_mark_read(): void
    {
        $this->postJson('/api/feedback', ['category' => 'Suggestion', 'subject' => 'Synthetic public suggestion',
            'message' => 'Synthetic private feedback body for the MISO office only.', 'contact_email' => 'private@example.invalid',
            'unverified_job_number' => 'UNVERIFIED-TEXT'])->assertCreated();
        $id = DB::table('general_feedback')->max('id');
        $admin = $this->user('admin');
        $notice = DB::table('portal_notifications')->where('general_feedback_id', $id)->first();
        $this->assertNotNull($notice); $this->assertNull($notice->service_request_id);
        $this->actingAs($admin)->getJson('/api/notifications?feed=1')->assertJsonPath('notifications.0.url', '/app/feedback/general/'.$id)
            ->assertDontSee('private@example.invalid')->assertDontSee('UNVERIFIED-TEXT')->assertDontSee('private feedback body');
        $this->actingAs($this->user('requester'))->postJson('/api/notifications/'.$notice->id.'/read')->assertNotFound();
        $office = $admin->office_id;
        $admin->office_id = DB::table('offices')->where('office_code', 'Science')->value('id'); $admin->save();
        $this->actingAs($admin)->getJson('/api/notifications?feed=1')->assertJsonCount(0, 'notifications');
        $this->postJson('/api/notifications/'.$notice->id.'/read')->assertNotFound();
        $admin->office_id = $office; $admin->save();
        $this->actingAs($admin)->postJson('/api/notifications/'.$notice->id.'/read')->assertOk();
        $read = DB::table('portal_notifications')->find($notice->id)->read_at;
        $this->travel(5)->minutes();
        $this->postJson('/api/notifications/'.$notice->id.'/read')->assertOk();
        $this->assertSame($read, DB::table('portal_notifications')->find($notice->id)->read_at);
        $this->getJson('/api/notifications?feed=1')->assertJsonPath('unread_count', 0);
    }

    public function test_evaluation_notifies_administrators_once_without_exposing_remarks_to_personnel(): void
    {
        $record = ServiceRequest::where('status', 'Completed')->whereNotIn('id', DB::table('service_evaluations')->select('request_id'))->firstOrFail();
        $owner = User::findOrFail($record->requester_id);
        $this->actingAs($owner)->postJson('/api/requests/'.$record->id.'/evaluation', ['rating' => 4, 'remarks' => 'Synthetic private evaluation.'])->assertCreated();
        $this->postJson('/api/requests/'.$record->id.'/evaluation', ['rating' => 5])->assertUnprocessable();
        $this->assertSame(1, DB::table('portal_notifications')->where('service_request_id', $record->id)->where('kind', 'evaluation')->count());
        $this->actingAs($this->user('admin'))->getJson('/api/notifications?feed=1')
            ->assertJsonPath('notifications.0.url', '/app/feedback/evaluation/'.$record->id)->assertDontSee('private evaluation');
        $this->actingAs($this->user('personnel'))->getJson('/api/notifications?feed=1')->assertDontSee('New service evaluation received.');
    }

    public function test_submission_and_alerts_roll_back_together(): void
    {
        $before = [];
        foreach (['service_requests', 'general_feedback', 'portal_notifications', 'audit_entries'] as $table) $before[$table] = DB::table($table)->count();
        try {
            DB::transaction(function () {
                app(RequestWorkflow::class)->create($this->user('requester'), $this->payload());
                app(GeneralFeedback::class)->submit(null, ['category' => 'Other', 'subject' => 'Synthetic rollback', 'message' => 'Synthetic rollback content.']);
                throw new \RuntimeException('Intentional outer rollback.');
            });
        } catch (\RuntimeException $error) { $this->assertSame('Intentional outer rollback.', $error->getMessage()); }
        foreach ($before as $table => $count) $this->assertDatabaseCount($table, $count);
    }

    public function test_unread_total_is_not_limited_to_the_latest_hundred_and_stale_record_access_is_filtered(): void
    {
        $owner = $this->user('requester');
        $record = ServiceRequest::where('requester_id', $owner->id)->firstOrFail();
        DB::table('portal_notifications')->where('user_id', $owner->id)->delete();
        $rows = array_fill(0, 105, ['user_id' => $owner->id, 'service_request_id' => $record->id, 'message' => 'Synthetic unread update.', 'created_at' => now()]);
        DB::table('portal_notifications')->insert($rows);
        $this->actingAs($owner)->getJson('/api/notifications?feed=1')->assertJsonCount(100, 'notifications')->assertJsonPath('unread_count', 105);
        $this->getJson('/api/notifications')->assertJsonCount(100);
        $notice = DB::table('portal_notifications')->where('user_id', $owner->id)->first();
        $record->requester_id = User::where('role', 'requester')->where('id', '!=', $owner->id)->value('id'); $record->save();
        $this->getJson('/api/notifications?feed=1')->assertJsonCount(0, 'notifications')->assertJsonPath('unread_count', 0);
        $this->postJson('/api/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->assertSame([], app(PortalNotifications::class)->feed($owner)['notifications']);
    }

    public function test_feedback_notification_foreign_key_is_enforced(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('portal_notifications')->insert(['user_id' => $this->user('admin')->id, 'kind' => 'feedback', 'general_feedback_id' => 999999, 'message' => 'Synthetic invalid target.', 'created_at' => now()]);
    }
}
