<?php
namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequesterDashboardService
{
    public function __construct(private RequestAccess $access) {}

    public function get(User $user): array
    {
        abort_unless($user->role === 'requester', 403);
        $base = $this->access->scope(ServiceRequest::query(), $user);
        $unrated = (clone $base)->where('status', 'Completed')->whereNotExists(
            fn ($q) => $q->selectRaw('1')->from('service_evaluations')
                ->whereColumn('request_id', 'service_requests.id')
        );
        $clarification = (clone $base)->where('status', 'For Clarification')->latest('updated_at')->first();
        $rating = (clone $unrated)->latest('completed_at')->first();
        return [
            'counts' => [
                'total' => (clone $base)->count(),
                'open' => (clone $base)->where('status', '!=', 'Completed')->count(),
                'completed' => (clone $base)->where('status', 'Completed')->count(),
                'awaiting_rating' => (clone $unrated)->count(),
            ],
            'recent' => (clone $base)->latest('created_at')->orderByDesc('id')->limit(4)->get()
                ->map(fn ($r) => $this->access->present($r, $user)),
            'clarification' => $clarification ? [
                'id' => $clarification->id, 'job_number' => $clarification->job_number,
                'message' => DB::table('request_events')->where('service_request_id', $clarification->id)
                    ->where('kind', 'Clarification requested')->where('visibility', 'requester')
                    ->latest('id')->value('message'),
            ] : null,
            'unrated' => $rating ? $this->access->present($rating, $user) : null,
            'updates' => DB::table('portal_notifications as n')
                ->join('service_requests as r', 'r.id', '=', 'n.service_request_id')
                ->where('n.user_id', $user->id)->where('r.requester_id', $user->id)
                ->orderByDesc('n.created_at')->orderByDesc('n.id')->limit(3)
                ->get(['n.id', 'n.service_request_id', 'n.message', 'n.created_at', 'r.job_number']),
        ];
    }
}
