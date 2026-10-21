<?php
namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FeedbackReader {
    private function general(User $user): Builder {
        abort_unless(in_array($user->role,['admin','requester'],true),403);
        $query=DB::table('general_feedback');
        if ($user->role==='requester') return $query->where('user_id',$user->id);
        // Independent public feedback is routed to MISO, not every office.
        if (DB::table('offices')->where('id',$user->office_id)->value('office_code')!=='MISO') $query->whereRaw('1=0');
        return $query;
    }
    private function evaluations(User $user): Builder {
        abort_unless(in_array($user->role,['admin','requester'],true),403);
        return DB::table('service_evaluations')->join('service_requests','service_requests.id','=','service_evaluations.request_id')
            ->where($user->role==='admin'?'service_requests.handling_office_id':'service_requests.requester_id',$user->role==='admin'?$user->office_id:$user->id)
            ->select('service_evaluations.*','service_requests.subject','service_requests.job_number');
    }
    public function index(User $user, array $filters): array {
        $generalQuery=$this->general($user);
        $evaluationQuery=$this->evaluations($user);
        $counts=['general'=>(clone $generalQuery)->count(),'evaluations'=>(clone $evaluationQuery)->count()];
        $this->filter($generalQuery,$filters,'general_feedback',['general_feedback.subject','message','unverified_job_number']);
        if (!empty($filters['category'])) $generalQuery->where('general_feedback.category',$filters['category']);
        $this->filter($evaluationQuery,$filters,'service_evaluations',['service_requests.subject','remarks','job_number']);
        $general=$generalQuery->orderByDesc('general_feedback.created_at')->orderByDesc('general_feedback.id')
            ->paginate(25,['*'],'general_page',$filters['general_page']??1);
        $evaluations=$evaluationQuery->orderByDesc('service_evaluations.created_at')->orderByDesc('request_id')
            ->paginate(25,['*'],'evaluation_page',$filters['evaluation_page']??1);
        return ['general'=>$general->items(),'evaluations'=>$evaluations->items(),
            'counts'=>$counts,
            'pagination'=>['general'=>$this->pagination($general),'evaluations'=>$this->pagination($evaluations)]];
    }
    private function filter(Builder $query, array $filters, string $table, array $searchColumns): void {
        if (trim($filters['search']??'') !== '') {
            // A literal substring, including entered %/_ characters, on SQLite and MySQL.
            $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($filters['search'])).'%';
            $query->where(function (Builder $nested) use ($searchColumns,$term) {
                foreach ($searchColumns as $column) $nested->orWhereRaw("$column LIKE ? ESCAPE '!'",[$term]);
            });
        }
        $rating=$filters['rating']??null;
        if ($rating==='unrated') $query->whereNull($table.'.rating');
        elseif ($rating==='low') $query->whereBetween($table.'.rating',[1,2]);
        elseif ($rating!==null && $rating!=='') $query->where($table.'.rating',(int)$rating);
        if (!empty($filters['days'])) {
            $now=CarbonImmutable::now('Asia/Manila');
            $query->where($table.'.created_at','>=',$now->startOfDay()->subDays((int)$filters['days']-1))
                ->where($table.'.created_at','<=',$now);
        }
    }
    private function pagination($page): array {
        return ['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'total'=>$page->total()];
    }
    public function detail(User $user, string $kind, int $id): array {
        if ($kind==='general') {
            $record=$this->general($user)->where('id',$id)->first();
        } elseif ($kind==='evaluation') {
            $record=$this->evaluations($user)
                ->leftJoin('service_types','service_types.id','=','service_requests.service_type_id')
                ->leftJoin('offices as requesting_office','requesting_office.id','=','service_requests.requesting_office_id')
                ->leftJoin('request_categories',function($join){
                    $join->on('request_categories.id','=','service_requests.category_id')
                        ->on('request_categories.handling_office_id','=','service_requests.handling_office_id');
                })
                ->addSelect('service_types.name as service_type','requesting_office.name as requesting_office',
                    'service_requests.completed_at','request_categories.name as request_category')
                // Same final-assignment definition as RequestAccess/reporting: greatest assignment ID.
                ->selectSub(DB::table('assignments')->join('users','users.id','=','assignments.personnel_id')
                    ->whereColumn('assignments.service_request_id','service_requests.id')
                    ->orderByDesc('assignments.id')->limit(1)->select('users.name'),'final_assigned_personnel')
                ->where('request_id',$id)->first();
        } else abort(404);
        abort_unless($record,404);
        return ['kind'=>$kind,'record'=>$record];
    }
}
