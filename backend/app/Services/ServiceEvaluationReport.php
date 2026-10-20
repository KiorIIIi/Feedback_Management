<?php
namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ServiceEvaluationReport {
    public function __construct(private RequestAccess $access) {}

    public function build(User $actor, array $filters): array {
        $this->access->admin($actor);
        return DB::transaction(function () use ($actor, $filters) {
            $asOf = CarbonImmutable::now('Asia/Manila');
            if (!empty($filters['month'])) {
                $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], 'Asia/Manila');
                $end = $start->addMonth();
            } else {
                $start = $asOf->startOfDay()->subDays(($filters['days'] ?? 30) - 1);
                $end = $asOf->startOfDay()->addDay();
            }
            $requests = $this->access->scope(ServiceRequest::query(), $actor)
                ->where('created_at', '>=', $start)->where('created_at', '<', $end)
                ->where('created_at', '<=', $asOf)->orderBy('id')->get();
            $ids = $requests->modelKeys();
            $categories = DB::table('request_categories')->where('handling_office_id', $actor->office_id)->pluck('name', 'id');
            $types = DB::table('service_types')->pluck('name', 'id');
            $offices = DB::table('offices')->pluck('name', 'id');
            $assignments = DB::table('assignments')->join('users', 'users.id', '=', 'assignments.personnel_id')
                ->whereIn('service_request_id', $ids)->orderByDesc('assignments.id')
                ->get(['assignments.*', 'users.name as personnel_name'])->groupBy('service_request_id');
            $ratings = DB::table('service_evaluations')->whereIn('request_id', $ids)
                ->whereBetween('rating', [1,5])->where('created_at', '<=', $asOf)->get()->keyBy('request_id');
            $rows = $requests->map(function ($request) use ($assignments, $ratings, $categories, $types, $offices, $asOf) {
                $history = $assignments->get($request->id, collect());
                $assignment = $request->status === 'Completed' ? $history->first() : $history->firstWhere('ended_at', null);
                $completedAt = $request->completed_at ? CarbonImmutable::parse($request->completed_at, 'Asia/Manila') : null;
                $createdAt = CarbonImmutable::parse($request->created_at, 'Asia/Manila');
                $seconds = $request->status === 'Completed' && $completedAt && $completedAt >= $createdAt && $completedAt <= $asOf
                    ? $createdAt->diffInSeconds($completedAt) : null;
                return [
                    'id'=>$request->id, 'job_number'=>$request->job_number, 'subject'=>$request->subject,
                    'status'=>$request->status, 'priority'=>$request->priority ?? 'Unreviewed',
                    'submitted_at'=>$createdAt->format('Y-m-d H:i:s'), 'completed_at'=>$completedAt?->format('Y-m-d H:i:s'),
                    'category_id'=>$request->category_id, 'category'=>$categories[$request->category_id] ?? 'Uncategorized',
                    'service_type'=>$types[$request->service_type_id] ?? 'Unknown service type',
                    'requesting_office'=>$offices[$request->requesting_office_id] ?? 'No office affiliation',
                    'personnel_id'=>$assignment?->personnel_id, 'personnel'=>$assignment?->personnel_name ?? 'Unassigned',
                    'rating'=>$request->status === 'Completed' ? ($ratings[$request->id]->rating ?? null) : null,
                    'resolution_seconds'=>$seconds,
                ];
            })->all();
            $concerns = [];
            foreach ($rows as $row) {
                $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $row['subject'])));
                if ($key === '') continue;
                $concerns[$key] ??= ['subject'=>$row['subject'], 'count'=>0, 'job_numbers'=>[]];
                $concerns[$key]['count']++;
                if (count($concerns[$key]['job_numbers']) < 5) $concerns[$key]['job_numbers'][]=$row['job_number'];
            }
            $concerns = array_values(array_filter($concerns, fn ($group) => $group['count'] >= 2));
            usort($concerns, fn ($a,$b) => ($b['count'] <=> $a['count']) ?: strcmp($a['subject'],$b['subject']));
            return [
                'version'=>'service-evaluation-v1', 'generated_at'=>$asOf->format('Y-m-d H:i:s'), 'timezone'=>'Asia/Manila',
                'office'=>$offices[$actor->office_id] ?? 'Handling office', 'handling_office_id'=>$actor->office_id,
                'period_start'=>$start->toDateString(), 'period_end'=>$end->subDay()->toDateString(),
                'definition'=>'Requests submitted in the selected period; statuses, categories and assignments reflect their current state at generation. Calendar elapsed hours, Asia/Manila; no business-hour or SLA adjustment.',
                'resolution_definition'=>'Sum of valid completion-minus-submission elapsed hours / eligible completed requests. Missing, negative or future completion times are excluded; open requests are excluded.',
                'rating_definition'=>'Average of available verified ratings on completed requests in this submission cohort. Missing ratings are excluded. General feedback is separate.',
                'concern_definition'=>'Repeated request subjects matched after case and whitespace normalization; at least two occurrences. No semantic or AI classification.',
                'summary'=>$this->summarize($rows),
                'categories'=>$this->groups($rows, 'category_id', 'category'),
                'priorities'=>$this->groups($rows, 'priority', 'priority'),
                'statuses'=>$this->groups($rows, 'status', 'status'),
                'personnel'=>$this->groups($rows, 'personnel_id', 'personnel'),
                'recurring_concerns'=>$concerns, 'requests'=>$rows,
            ];
        });
    }

    private function summarize(array $rows): array {
        $ratings = array_values(array_filter(array_column($rows, 'rating'), fn ($rating) => $rating !== null));
        $durations = array_values(array_filter(array_column($rows, 'resolution_seconds'), fn ($seconds) => $seconds !== null));
        $completed = count(array_filter($rows, fn ($row) => $row['status'] === 'Completed'));
        return [
            'total'=>count($rows), 'completed'=>$completed, 'open'=>count($rows)-$completed,
            'completion_rate'=>count($rows) ? round($completed/count($rows)*100, 1) : 0,
            'rating_count'=>count($ratings), 'average_rating'=>count($ratings) ? round(array_sum($ratings)/count($ratings), 2) : null,
            'resolution_count'=>count($durations), 'resolution_excluded'=>$completed-count($durations),
            'average_resolution_hours'=>count($durations) ? round(array_sum($durations)/count($durations)/3600, 2) : null,
        ];
    }

    private function groups(array $rows, string $key, string $label): array {
        $groups = collect($rows)->groupBy(fn ($row) => $row[$key] ?? '__unassigned');
        return $groups->map(fn ($items) => [
            'id'=>$items->first()[$key], 'name'=>$items->first()[$label], ...$this->summarize($items->all()),
        ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
