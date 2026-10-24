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
            $id = DB::table('general_feedback')->insertGetId([...$data, 'user_id' => $actor?->id, 'created_at' => now()]);
            DB::table('audit_entries')->insert([
                'actor_user_id' => $actor?->id, 'actor_kind' => $actor ? 'human' : 'anonymous',
                'action' => 'feedback.submitted', 'occurred_at' => now(),
            ]);
            $this->notifications->feedback($id, $actor?->id);
        });
    }
}
