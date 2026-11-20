<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GeneralFeedback
{
    public function __construct(private PortalNotifications $notifications, private FeedbackForms $forms) {}

    public function submit(?User $actor, array $data): void
    {
        $form = null;
        if (!empty($data['feedback_form_id'])) {
            $form = DB::table('feedback_forms')->where('id', (int) $data['feedback_form_id'])->first();
            $this->forms->assertAccepting($form);
        }
        DB::transaction(function () use ($actor, $data, $form) {
            $id = DB::table('general_feedback')->insertGetId([
                'category' => $data['category'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'unverified_job_number' => $data['unverified_job_number'] ?? null,
                'rating' => $data['rating'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'source_label' => $data['source_label'] ?? null,
                'feedback_form_id' => $form?->id,
                'user_id' => $actor?->id, 'created_at' => now(),
            ]);
            DB::table('audit_entries')->insert([
                'actor_user_id' => $actor?->id, 'actor_kind' => $actor ? 'human' : 'anonymous',
                'action' => 'feedback.submitted', 'occurred_at' => now(),
                // Identify the submitted record so anonymous feedback remains attributable (A14).
                'after' => json_encode(['general_feedback_id' => $id, 'feedback_form_id' => $form?->id]),
            ]);
            $this->notifications->feedback($id, $actor?->id);
        });
    }
}
