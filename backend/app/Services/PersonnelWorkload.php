<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PersonnelWorkload
{
    public function __construct(private RequestAccess $access) {}

    public function get(User $user, ?int $page = null, ?int $serviceType = null): array
    {
        $this->access->admin($user);
        $query = User::where('role', 'personnel')->where('office_id', $user->office_id)->where('is_active', true);
        if ($serviceType !== null) {
            $query->whereIn('id', DB::table('personnel_eligibilities')->where('service_type_id', $serviceType)->select('user_id'));
        }
        $query->orderBy('id');
        $pagination = $page === null ? null : (clone $query)->paginate(20, ['id', 'name', 'office_id'], 'page', $page);
        $people = $pagination ? $pagination->getCollection() : $query->get(['id', 'name', 'office_id']);
        $ids = $people->modelKeys();
        $eligibilities = DB::table('personnel_eligibilities')->whereIn('user_id', $ids)->get()->groupBy('user_id');
        $counts = DB::table('assignments')->join('service_requests', 'service_requests.id', '=', 'assignments.service_request_id')
            ->whereIn('personnel_id', $ids)->where('handling_office_id', $user->office_id)
            ->whereNull('ended_at')->where('status', '!=', 'Completed')
            ->groupBy('personnel_id')->selectRaw('personnel_id, COUNT(*) as total')->pluck('total', 'personnel_id');
        $data = $people->map(fn ($person) => [
            'id' => $person->id, 'name' => $person->name, 'office_id' => $person->office_id,
            'active_assignments' => (int) ($counts[$person->id] ?? 0),
            'service_type_ids' => $eligibilities->get($person->id, collect())->pluck('service_type_id')->all(),
            'recorded_availability' => null,
        ])->all();
        return [
            'data' => $data, 'total' => $pagination?->total() ?? count($data),
            'current_page' => $pagination?->currentPage() ?? 1, 'last_page' => $pagination?->lastPage() ?? 1,
            'availability_definition' => 'Availability is not recorded in this prototype; active account status and assignment count do not establish physical availability.',
        ];
    }
}
