<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportJobReader
{
    public function authorizedJob(User $user, int $id): object
    {
        $job = DB::table('report_jobs')->where('id', $id)->where('requested_by', $user->id)->first();
        abort_unless($job, 404);
        $office = json_decode($job->filters, true)['handling_office_id'] ?? null;
        abort_unless($user->role === 'admin' && $user->is_active && (int) $office === (int) $user->office_id, 403);
        return $job;
    }

    public function show(User $user, int $id): array
    {
        $job = $this->authorizedJob($user, $id);
        $format = json_decode($job->filters, true)['format'] ?? 'csv';
        $ready = in_array($format, ['csv', 'pdf', 'xlsx'], true) && $job->status === 'completed'
            && $job->path === 'reports/'.$id.'.'.$format && Storage::disk('local')->exists($job->path);
        return [
            'id' => $id, 'status' => $job->status, 'format' => $format,
            'error' => $job->status === 'completed' && !$ready ? 'The export file is no longer available. Generate a new report.' : $job->error,
            'download_url' => $ready ? '/api/reports/'.$id.'/download' : null,
        ];
    }
}
