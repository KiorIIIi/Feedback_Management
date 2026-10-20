<?php
namespace App\Http\Controllers;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\RequestAccess;
use App\Services\RequestWorkflow;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class PortalController {
 public function login(Request $request){
  $data=$request->validate(['email'=>'required|email','password'=>'required|string','remember'=>'sometimes|boolean','role'=>['sometimes',Rule::in(['admin','personnel','requester','staff'])]]);
  $credentials=['email'=>$data['email'],'password'=>$data['password'],'is_active'=>true];
  if(isset($data['role']))$credentials['role']=$data['role']==='staff'?['admin','personnel']:$data['role'];
  if(!Auth::attempt($credentials,$data['remember']??false))return response()->json(['message'=>isset($data['role'])?'Use a valid '.$data['role'].' account and password for this sign-in.':'Email or password is incorrect.'],422);
  $request->session()->regenerate();return $request->user();
 }
 public function logout(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return response()->json(['message'=>'Signed out.']);}
 public function catalog(Request $request){
  $user=$request->user();$personnel=[];
  if($user->role==='admin')$personnel=User::where('role','personnel')->where('office_id',$user->office_id)->where('is_active',true)->get(['id','name','office_id'])->map(function($p){$p->active_assignments=DB::table('assignments')->where('personnel_id',$p->id)->whereNull('ended_at')->count();$p->service_type_ids=DB::table('personnel_eligibilities')->where('user_id',$p->id)->pluck('service_type_id');return $p;});
  return ['offices'=>DB::table('offices')->whereIn('id',DB::table('office_memberships')->where('user_id',$user->id)->select('office_id'))->get(),'service_types'=>DB::table('service_types')->get(),'personnel'=>$personnel,'policy_mode'=>config('workflow.policy_mode')];
 }
 public function index(Request $request,RequestAccess $access){
  $data=$request->validate(['search'=>'nullable|string|max:200','status'=>'nullable|string|max:40','priority'=>'nullable|string|max:20','personnel_id'=>'nullable|integer','page'=>'nullable|integer|min:1']);
  $q=$access->scope(ServiceRequest::query(),$request->user());
  if($data['search']??false){$s=$data['search'];$q->where(fn($q)=>$q->where('subject','like',"%$s%")->orWhere('job_number','like',"%$s%"));}
  foreach(['status','priority'] as $f)if($data[$f]??false)$q->where($f,$data[$f]);
  if($data['personnel_id']??false)$q->whereExists(fn($s)=>$s->selectRaw('1')->from('assignments')->whereColumn('service_request_id','service_requests.id')->where('personnel_id',$data['personnel_id'])->whereNull('ended_at'));
  $rows=$q->orderByDesc('id')->paginate(20);return ['data'=>$rows->getCollection()->map(fn($r)=>$access->present($r,$request->user())),'total'=>$rows->total(),'current_page'=>$rows->currentPage(),'last_page'=>$rows->lastPage()];
 }
 public function show(Request $request,int $id,RequestAccess $access){return $access->present(ServiceRequest::findOrFail($id),$request->user(),true);}
 public function store(Request $request,RequestWorkflow $workflow,RequestAccess $access){
  $data=$request->validate(['requesting_office_id'=>'nullable|integer|exists:offices,id','service_type_id'=>'required|integer|exists:service_types,id','subject'=>'required|string|max:200','description'=>'required|string|min:15|max:10000','location'=>'required|string|max:255','urgency'=>['required',Rule::in(['Low','Medium','High'])],'impact'=>['required',Rule::in(['Individual','One room','Entire office','Campus-wide'])]]);
  $r=$workflow->create($request->user(),$data);return response()->json($access->present($r,$request->user(),true),201);
 }
 public function update(Request $request,int $id,RequestWorkflow $workflow,RequestAccess $access){
  $data=$request->validate(['expected_version'=>'required|integer|min:1','action'=>['required',Rule::in(['manage','clarify','reply'])],'message'=>'nullable|string|max:10000','status'=>['sometimes',Rule::in(['Pending','For Clarification','Assigned','In Progress','On Hold','Completed'])],'personnel_id'=>'sometimes|nullable|integer|exists:users,id','priority'=>['sometimes',Rule::in(['Low','Medium','High','Critical'])],'reason'=>'nullable|string|max:2000','visibility'=>['sometimes',Rule::in(['requester','internal'])],'outcome'=>'nullable|string|max:5000']);
  $r=$workflow->update($request->user(),$id,$data);return $access->present($r,$request->user(),true);
 }
 public function evaluate(Request $request,int $id,RequestWorkflow $workflow){$data=$request->validate(['rating'=>'required|integer|min:1|max:5','category'=>['nullable',Rule::in(['Complaint','Suggestion','Compliment','Other'])],'remarks'=>'nullable|string|max:5000']);$workflow->evaluate($request->user(),$id,$data);return response()->json(['message'=>'Thank you. Your service evaluation has been recorded.'],201);}
 public function feedback(Request $request){
  $data=$request->validate(['category'=>['required',Rule::in(['Complaint','Suggestion','Compliment','Other'])],'subject'=>'required|string|max:200','message'=>'required|string|min:10|max:10000','unverified_job_number'=>'nullable|string|max:100','rating'=>'nullable|integer|min:1|max:5','contact_name'=>'nullable|string|max:100','contact_email'=>'nullable|email|max:200']);
  DB::transaction(function()use($data,$request){DB::table('general_feedback')->insert([...$data,'user_id'=>$request->user()?->id,'created_at'=>now()]);DB::table('audit_entries')->insert(['actor_user_id'=>$request->user()?->id,'actor_kind'=>$request->user()?'human':'anonymous','action'=>'feedback.submitted','occurred_at'=>now()]);});
  return response()->json(['message'=>'Thank you. Your feedback has been received for private review.'],201);
 }
 public function feedbackIndex(Request $request){
  $user=$request->user();$general=DB::table('general_feedback');if($user->role!=='admin')$general->where('user_id',$user->id);
  $verified=DB::table('service_evaluations')->join('service_requests','service_requests.id','=','service_evaluations.request_id');
  if($user->role==='admin'){$verified->where('handling_office_id',$user->office_id);$general->whereRaw('1=0');}else $verified->where('requester_id',$user->id);
  // Public feedback is routed to MISO only, never all offices.
  if($user->role==='admin'&&DB::table('offices')->where('id',$user->office_id)->value('office_code')==='MISO')$general=DB::table('general_feedback');
  if($user->role==='personnel')abort(403);
  return ['general'=>$general->orderByDesc('id')->limit(100)->get(),'evaluations'=>$verified->select('service_evaluations.*','subject','job_number')->orderByDesc('service_evaluations.created_at')->limit(100)->get()];
 }
 public function metrics(Request $request,ReportingService $reports){$data=$request->validate(['days'=>'sometimes|integer|min:1|max:366']);return $reports->metrics($request->user(),$data['days']??30);}
 public function notifications(Request $request){return DB::table('portal_notifications')->where('user_id',$request->user()->id)->orderByDesc('id')->limit(100)->get();}
 public function readNotification(Request $request,int $id){$notice=DB::table('portal_notifications')->where('id',$id)->where('user_id',$request->user()->id);abort_unless((clone $notice)->exists(),404);$notice->whereNull('read_at')->update(['read_at'=>now()]);return ['message'=>'Marked as read.'];}
}
