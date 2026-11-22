<?php
namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FeedbackForms {
    public function __construct(private RequestAccess $access) {}

    private function status(?bool $active, $opensAt, $closesAt): string {
        if (!$active) return 'closed';
        $now = CarbonImmutable::now('Asia/Manila');
        if ($opensAt !== null && CarbonImmutable::parse($opensAt)->gt($now)) return 'scheduled';
        if ($closesAt !== null && CarbonImmutable::parse($closesAt)->lt($now)) return 'expired';
        return 'open';
    }

    /** The form record accepting submissions must satisfy, or submission is refused. */
    public function assertAccepting($form): void {
        abort_unless($form !== null, 404, 'This feedback form does not exist.');
        abort_unless($form->is_active, 422, 'This form has been closed and no longer accepts responses.');
        $now = CarbonImmutable::now('Asia/Manila');
        if ($form->opens_at !== null && CarbonImmutable::parse($form->opens_at)->gt($now)) {
            abort(422, 'This form has not opened yet. Please return during its collection window.');
        }
        if ($form->closes_at !== null && CarbonImmutable::parse($form->closes_at)->lt($now)) {
            abort(422, 'This form has expired and no longer accepts responses.');
        }
    }

    public function create(User $actor, array $data): object {
        $this->access->admin($actor);
        if (($data['opens_at'] ?? null) && ($data['closes_at'] ?? null)
            && CarbonImmutable::parse($data['closes_at'])->lt(CarbonImmutable::parse($data['opens_at']))) {
            abort(422, 'The close time must not be before the open time.');
        }
        return DB::transaction(function () use ($actor, $data) {
            $id = DB::table('feedback_forms')->insertGetId([
                'token' => bin2hex(random_bytes(16)),
                'created_by' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'opens_at' => $data['opens_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
                'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('audit_entries')->insert([
                'actor_user_id' => $actor->id, 'actor_kind' => 'human', 'action' => 'feedback_form.created',
                'after' => json_encode(['feedback_form_id' => $id, 'title' => $data['title']]),
                'occurred_at' => now(),
            ]);
            return DB::table('feedback_forms')->find($id);
        });
    }

    public function update(User $actor, int $id, array $data): void {
        $this->access->admin($actor);
        DB::transaction(function () use ($actor, $id, $data) {
            $form = DB::table('feedback_forms')->where('id', $id)->lockForUpdate()->first();
            abort_unless($form, 404);
            $changes = array_filter([
                'title' => $data['title'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'opens_at' => array_key_exists('opens_at', $data) ? ($data['opens_at'] ?: null) : null,
                'closes_at' => array_key_exists('closes_at', $data) ? ($data['closes_at'] ?: null) : null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($value) => $value !== null);
            abort_unless($changes !== [], 422);
            $changes['updated_at'] = now();
            $opensAt = $changes['opens_at'] ?? $form->opens_at;
            $closesAt = $changes['closes_at'] ?? $form->closes_at;
            if ($opensAt && $closesAt && CarbonImmutable::parse($closesAt)->lt(CarbonImmutable::parse($opensAt))) {
                abort(422, 'The close time must not be before the open time.');
            }
            DB::table('feedback_forms')->where('id', $id)->update($changes);
            DB::table('audit_entries')->insert([
                'actor_user_id' => $actor->id, 'actor_kind' => 'human', 'action' => 'feedback_form.updated',
                'after' => json_encode(['feedback_form_id' => $id, 'changes' => $changes]),
                'occurred_at' => now(),
            ]);
        });
    }

    public function list(User $actor): array {
        $this->access->admin($actor);
        $forms = DB::table('feedback_forms')->orderByDesc('id')->get();
        $counts = DB::table('general_feedback')->whereNotNull('feedback_form_id')
            ->select('feedback_form_id', DB::raw('COUNT(*) as total'))->groupBy('feedback_form_id')->pluck('total', 'feedback_form_id');
        return $forms->map(fn ($form) => [
            'id' => $form->id, 'token' => $form->token, 'title' => $form->title, 'description' => $form->description,
            'opens_at' => $form->opens_at, 'closes_at' => $form->closes_at, 'is_active' => (bool) $form->is_active,
            'status' => $this->status((bool) $form->is_active, $form->opens_at, $form->closes_at),
            'responses' => (int) ($counts[$form->id] ?? 0),
            'created_at' => $form->created_at,
        ])->all();
    }

    /** Public metadata for the shareable link; exposes only this form's public fields. */
    public function publicByToken(string $token): array {
        $form = DB::table('feedback_forms')->where('token', $token)->first();
        abort_unless($form, 404);
        return [
            'id' => $form->id,
            'title' => $form->title, 'description' => $form->description,
            'opens_at' => $form->opens_at, 'closes_at' => $form->closes_at,
            'status' => $this->status((bool) $form->is_active, $form->opens_at, $form->closes_at),
        ];
    }
}
