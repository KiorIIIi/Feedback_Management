<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Service evaluation report</title>
<style>
@page { margin: 28pt 30pt 38pt; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e3029; }
h1 { color: #12553d; font-size: 22pt; margin: 3pt 0 12pt; }
h2 { font-size: 13pt; margin: 20pt 0 8pt; page-break-after: avoid; }
p { line-height: 1.5; margin: 4pt 0; }
.brand { font-size: 10pt; color: #12553d; }
.meta, .note { color: #52665c; font-size: 8pt; }
.rule { border-bottom: 2pt solid #12553d; padding-bottom: 12pt; margin-bottom: 12pt; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { text-align: left; background: #eaf3ed; color: #12553d; font-size: 8pt; }
th, td { padding: 7pt 6pt; border-bottom: .5pt solid #dce3de; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
thead { display: table-header-group; } tr { page-break-inside: avoid; }
.number { text-align: right; } .detail { page-break-before: always; } .detail table { font-size: 7.5pt; }
.summary td { width: 25%; } .summary strong { font-size: 16pt; color: #12553d; }
.section-heading { background: white; border: 0; padding: 0 0 6pt; font-weight: normal; }
.section-heading h2 { color: #1e3029; }
</style></head><body>
<div class="rule"><p class="brand">CENTRAL LUZON STATE UNIVERSITY · MISO SERVICE PORTAL</p>
<h1>Service evaluation report</h1><p>{{ $report['office'] }}</p>
<p class="meta">Submission period: {{ $report['period_start'] }} to {{ $report['period_end'] }} · Generated {{ $report['generated_at'] }} ({{ $report['timezone'] }})</p></div>
<table class="summary"><tr>
<td>Requests received<br><strong>{{ $report['summary']['total'] }}</strong></td>
<td>Completed<br><strong>{{ $report['summary']['completed'] }}</strong></td>
<td>Open<br><strong>{{ $report['summary']['open'] }}</strong></td>
<td>Completion rate<br><strong>{{ $report['summary']['completion_rate'] }}%</strong></td>
</tr><tr>
<td>Average verified rating<br><strong>{{ $report['summary']['average_rating'] ?? '—' }}</strong><br>{{ $report['summary']['rating_count'] }} verified ratings · scale 1–5</td>
<td>Average resolution<br><strong>{{ $report['summary']['average_resolution_hours'] ?? '—' }}</strong><br>calendar elapsed hours</td>
<td>Eligible completed requests<br><strong>{{ $report['summary']['resolution_count'] }}</strong></td>
<td>Invalid/missing completion times<br><strong>{{ $report['summary']['resolution_excluded'] }}</strong><br>excluded from average</td>
</tr></table>
<p class="note">{{ $report['definition'] }}</p><p class="note">{{ $report['rating_definition'] }}</p><p class="note">{{ $report['resolution_definition'] }}</p>
@foreach (['categories'=>'Requests by category','priorities'=>'Requests by priority','statuses'=>'Requests by status','personnel'=>'Requests by assigned personnel'] as $key=>$title)
<table><thead><tr><th colspan="7" class="section-heading"><h2>{{ $title }}</h2>
@if ($key==='personnel')<p class="note">One active assignee per open request; final assignee for completed requests. Each request is counted once.</p>@endif
</th></tr><tr><th style="width:32%">Group</th><th class="number">Requests</th><th class="number">Completed</th><th class="number">Avg. rating</th><th class="number">Ratings</th><th class="number">Avg. hours</th><th class="number">Eligible completed</th></tr></thead><tbody>
@forelse ($report[$key] as $group)<tr><td>{{ $group['name'] }}</td><td class="number">{{ $group['total'] }}</td><td class="number">{{ $group['completed'] }}</td><td class="number">{{ $group['average_rating'] ?? '—' }}</td><td class="number">{{ $group['rating_count'] }}</td><td class="number">{{ $group['average_resolution_hours'] ?? '—' }}</td><td class="number">{{ $group['resolution_count'] }}</td></tr>
@empty<tr><td colspan="7">No requests in the selected period.</td></tr>@endforelse
</tbody></table>
@endforeach
<h2>Recurring concerns</h2><p class="note">{{ $report['concern_definition'] }}</p>
<table><thead><tr><th style="width:40%">Repeated subject</th><th style="width:12%">Occurrences</th><th>Example job numbers (up to five)</th></tr></thead><tbody>
@forelse ($report['recurring_concerns'] as $concern)<tr><td>{{ $concern['subject'] }}</td><td>{{ $concern['count'] }}</td><td>{{ implode(', ', $concern['job_numbers']) }}</td></tr>
@empty<tr><td colspan="3">No repeated request subjects in this period.</td></tr>@endforelse
</tbody></table>
<section class="detail"><h2>Request register</h2><p class="note">No internal notes, contact details, or unverified general-feedback ratings are included. All dates use Asia/Manila.</p>
<table><thead><tr><th style="width:30%">Request / category / service type</th><th style="width:13%">Assigned personnel</th><th style="width:10%">Status</th><th style="width:9%">Priority</th><th style="width:11%">Submitted</th><th style="width:11%">Completed</th><th style="width:6%">Rating</th><th style="width:10%">Elapsed hours</th></tr></thead><tbody>
@forelse ($report['requests'] as $request)<tr><td><strong>{{ $request['job_number'] }}</strong><br>{{ $request['subject'] }}<br><span class="note">{{ $request['category'] }} · {{ $request['service_type'] }}</span></td><td>{{ $request['personnel'] }}</td><td>{{ $request['status'] }}</td><td>{{ $request['priority'] }}</td><td>{{ $request['submitted_at'] }}</td><td>{{ $request['completed_at'] ?? '—' }}</td><td>{{ $request['rating'] ?? '—' }}</td><td>{{ $request['resolution_seconds'] === null ? '—' : round($request['resolution_seconds']/3600,2) }}</td></tr>
@empty<tr><td colspan="8">No requests in the selected period.</td></tr>@endforelse
</tbody></table></section>
</body></html>
