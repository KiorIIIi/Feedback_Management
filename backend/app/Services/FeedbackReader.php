<?php
namespace App\Services;

use App\Models\User;
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
            ->where($user->role==='admin'?'handling_office_id':'requester_id',$user->role==='admin'?$user->office_id:$user->id)
            ->select('service_evaluations.*','subject','job_number');
    }
    public function index(User $user, array $filters): array {
        $general=$this->general($user)->orderByDesc('id')->paginate(25,['*'],'general_page',$filters['general_page']??1);
        $evaluations=$this->evaluations($user)->orderByDesc('service_evaluations.created_at')->orderByDesc('request_id')
            ->paginate(25,['*'],'evaluation_page',$filters['evaluation_page']??1);
        return ['general'=>$general->items(),'evaluations'=>$evaluations->items(),
            'pagination'=>['general'=>$this->pagination($general),'evaluations'=>$this->pagination($evaluations)]];
    }
    private function pagination($page): array {
        return ['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'total'=>$page->total()];
    }
    public function detail(User $user, string $kind, int $id): array {
        if ($kind==='general') {
            $record=$this->general($user)->where('id',$id)->first();
        } elseif ($kind==='evaluation') {
            $record=$this->evaluations($user)->where('request_id',$id)->first();
        } else abort(404);
        abort_unless($record,404);
        return ['kind'=>$kind,'record'=>$record];
    }
}
