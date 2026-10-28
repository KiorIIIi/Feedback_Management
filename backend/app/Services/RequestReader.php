<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;

class RequestReader
{
    public function __construct(private RequestAccess $access) {}

    public function search(User $user, array $filters): array
    {
        $query = $this->access->scope(ServiceRequest::query(), $user);
        if (trim($filters['search'] ?? '') !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->where(fn ($q) => $q->whereRaw("subject LIKE ? ESCAPE '!'", [$term])->orWhereRaw("job_number LIKE ? ESCAPE '!'", [$term]));
        }
        foreach (['status', 'priority'] as $field) {
            if (!empty($filters[$field])) $query->where($field, $filters[$field]);
        }
        if (!empty($filters['personnel_id'])) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('assignments')
                ->whereColumn('service_request_id', 'service_requests.id')->where('personnel_id', $filters['personnel_id'])->whereNull('ended_at'));
        }
        $page = $query->orderByDesc('id')->paginate(20, ['*'], 'page', $filters['page'] ?? 1);
        return [
            'data' => $page->getCollection()->map(fn ($record) => $this->access->present($record, $user)),
            'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ];
    }
}
