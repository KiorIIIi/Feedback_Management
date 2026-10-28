<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PortalNotifications
{
    public function __construct(private RequestAccess $access, private FeedbackReader $feedback) {}

    public function officeAdministrators(int $office, ?int $except = null): array
    {
        return User::where('role', 'admin')->where('is_active', true)->where('office_id', $office)
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except))->pluck('id')->all();
    }

    public function request(ServiceRequest $request, string $message, array $recipients, string $kind = 'request'): void
    {
        foreach (array_unique($recipients) as $recipient) {
            DB::table('portal_notifications')->insert([
                'user_id' => $recipient, 'kind' => $kind, 'service_request_id' => $request->id,
                'message' => $message, 'created_at' => now(),
            ]);
        }
    }

    public function feedback(int $id, ?int $actor): void
    {
        $office = DB::table('offices')->where('office_code', 'MISO')->value('id');
        if ($office === null) return;
        foreach ($this->officeAdministrators($office, $actor) as $recipient) {
            DB::table('portal_notifications')->insert([
                'user_id' => $recipient, 'kind' => 'feedback', 'general_feedback_id' => $id,
                'message' => 'New feedback received.', 'created_at' => now(),
            ]);
        }
    }

    private function scope(User $user): Builder
    {
        $requests = $this->access->scope(ServiceRequest::query(), $user)->selectRaw('1')
            ->whereColumn('service_requests.id', 'n.service_request_id')->toBase();
        return DB::table('portal_notifications as n')->where('n.user_id', $user->id)->when(!$user->is_active, fn ($query) => $query->whereRaw('1=0'))
            ->where(function (Builder $query) use ($user, $requests) {
                $query->where(function (Builder $request) use ($user, $requests) {
                    $request->whereNull('n.general_feedback_id')->whereExists($requests)
                        ->where(function (Builder $kind) use ($user) {
                            $kind->where('n.kind', 'request');
                            if (in_array($user->role, ['admin', 'requester'], true)) $kind->orWhere('n.kind', 'evaluation');
                        });
                });
                if (in_array($user->role, ['admin', 'requester'], true)) {
                    $feedback = $this->feedback->generalScope($user)->selectRaw('1')->whereColumn('general_feedback.id', 'n.general_feedback_id');
                    $query->orWhere(fn (Builder $item) => $item->where('n.kind', 'feedback')->whereNull('n.service_request_id')->whereExists($feedback));
                }
            });
    }

    public function feed(User $user): array
    {
        $query = $this->scope($user);
        return [
            'unread_count' => (clone $query)->whereNull('n.read_at')->count(),
            'notifications' => $query->orderByDesc('n.id')->limit(100)
                ->get(['n.id', 'n.kind', 'n.service_request_id', 'n.general_feedback_id', 'n.message', 'n.read_at', 'n.created_at'])
                ->map(function ($notice) {
                    $notice->url = match ($notice->kind) {
                        'feedback' => '/app/feedback/general/'.$notice->general_feedback_id,
                        'evaluation' => '/app/feedback/evaluation/'.$notice->service_request_id,
                        default => '/app/requests/'.$notice->service_request_id,
                    };
                    return $notice;
                })->all(),
        ];
    }

    public function markRead(User $user, int $id): void
    {
        $query = $this->scope($user)->where('n.id', $id);
        abort_unless((clone $query)->exists(), 404);
        $query->whereNull('n.read_at')->update(['read_at' => now()]);
    }
}
