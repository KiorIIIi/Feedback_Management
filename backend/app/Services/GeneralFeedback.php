<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GeneralFeedback
{
    public function __construct(private PortalNotifications $notifications) {}

    public function submit(?User $actor, array $data): void
    {
        DB::transaction(function () use ($actor, $data) {
            $id = DB::table('general_feedback')->insertGetId([
                'category' => $data['category'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'unverified_job_number' => $data['unverified_job_number'] ?? null,
                'rating' => $data['rating'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'source_label' => $data['source_label'] ?? null,
                'user_id' => $actor?->id, 'created_at' => now(),
            ]);
            DB::table('audit_entries')->insert([
                'actor_user_id' => $actor?->id, 'actor_kind' => $actor ? 'human' : 'anonymous',
                'action' => 'feedback.submitted', 'occurred_at' => now(),
            ]);
            $this->notifications->feedback($id, $actor?->id);
        });
    }
}
