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
  if($user->role==='admin')$personnel=app(\App\Services\PersonnelWorkload::class)->get($user)['data'];
  return ['offices'=>DB::table('offices')->whereIn('id',DB::table('office_memberships')->where('user_id',$user->id)->select('office_id'))->get(),'service_types'=>DB::table('service_types')->get(),'personnel'=>$personnel,'categories'=>$user->role==='admin'?DB::table('request_categories')->where('handling_office_id',$user->office_id)->orderBy('name')->get(['id','name']):[],'policy_mode'=>config('workflow.policy_mode')];
 }
 public function createCategory(Request $request,\App\Services\RequestCategories $categories){$data=$request->validate(['name'=>'required|string|max:80']);return response()->json($categories->create($request->user(),$data['name']),201);}
 public function index(Request $request,RequestAccess $access){
  $data=$request->validate(['search'=>'nullable|string|max:200','status'=>'nullable|string|max:40','priority'=>'nullable|string|max:20','personnel_id'=>'nullable|integer','page'=>'nullable|integer|min:1']);
  return app(\App\Services\RequestReader::class)->search($request->user(),$data);
 }
 public function show(Request $request,int $id,RequestAccess $access){return $access->present(ServiceRequest::findOrFail($id),$request->user(),true);}
 public function store(Request $request,RequestWorkflow $workflow,RequestAccess $access){
  $data=$request->validate(['requesting_office_id'=>'nullable|integer|exists:offices,id','service_type_id'=>'required|integer|exists:service_types,id','subject'=>'required|string|max:200','description'=>'required|string|min:15|max:10000','location'=>'required|string|max:255','urgency'=>['required',Rule::in(['Low','Medium','High'])],'impact'=>['required',Rule::in(['Individual','One room','Entire office','Campus-wide'])]]);
  $r=$workflow->create($request->user(),$data);return response()->json($access->present($r,$request->user(),true),201);
 }
 public function update(Request $request,int $id,RequestWorkflow $workflow,RequestAccess $access){
  $data=$request->validate(['expected_version'=>'required|integer|min:1','action'=>['required',Rule::in(['manage','clarify','reply'])],'message'=>'nullable|string|max:10000','status'=>['sometimes',Rule::in(['Pending','For Clarification','Assigned','In Progress','On Hold','Completed'])],'personnel_id'=>'sometimes|nullable|integer|exists:users,id','category_id'=>'sometimes|nullable|integer|exists:request_categories,id','priority'=>['sometimes',Rule::in(['Low','Medium','High','Critical'])],'reason'=>'nullable|string|max:2000','visibility'=>['sometimes',Rule::in(['requester','internal'])],'outcome'=>'nullable|string|max:5000']);
  $r=$workflow->update($request->user(),$id,$data);return $access->present($r,$request->user(),true);
 }
 public function evaluate(Request $request,int $id,RequestWorkflow $workflow){$data=$request->validate(['rating'=>'required|integer|min:1|max:5','category'=>['nullable',Rule::in(['Complaint','Suggestion','Compliment','Other'])],'remarks'=>'nullable|string|max:5000']);$workflow->evaluate($request->user(),$id,$data);return response()->json(['message'=>'Thank you. Your service evaluation has been recorded.'],201);}
 public function feedback(Request $request){
  $data=$request->validate(['category'=>['required',Rule::in(['Complaint','Suggestion','Compliment','Other'])],'subject'=>'required|string|max:200','message'=>'required|string|min:10|max:10000','unverified_job_number'=>'nullable|string|max:100','rating'=>'nullable|integer|min:1|max:5','contact_name'=>'nullable|string|max:100','contact_email'=>'nullable|email|max:200']);
  app(\App\Services\GeneralFeedback::class)->submit($request->user(),$data);
  return response()->json(['message'=>'Thank you. Your feedback has been received for private review.'],201);
 }
 public function feedbackIndex(Request $request,\App\Services\FeedbackReader $feedback){
  $filters=$request->validate([
   'general_page'=>'sometimes|integer|min:1','evaluation_page'=>'sometimes|integer|min:1',
   'search'=>'nullable|string|max:200',
   'rating'=>['nullable',Rule::in(['low','unrated','1','2','3','4','5'])],
   'days'=>['nullable',Rule::in(['7','30','90'])],
   'category'=>['nullable',Rule::in(['Complaint','Suggestion','Compliment','Other'])],
  ]);
  return $feedback->index($request->user(),$filters);
 }
 public function feedbackShow(Request $request,string $kind,int $id,\App\Services\FeedbackReader $feedback){
  return $feedback->detail($request->user(),$kind,$id);
 }
 public function metrics(Request $request,ReportingService $reports){$data=$request->validate(['days'=>'sometimes|integer|min:1|max:366']);return $reports->metrics($request->user(),$data['days']??30);}
 public function notifications(Request $request,\App\Services\PortalNotifications $notifications){$data=$request->validate(['feed'=>'sometimes|boolean']);$feed=$notifications->feed($request->user());return response()->json(($data['feed']??false)?$feed:$feed['notifications'])->header('Cache-Control','private, no-store');}
 public function readNotification(Request $request,int $id,\App\Services\PortalNotifications $notifications){$notifications->markRead($request->user(),$id);return ['message'=>'Marked as read.'];}
}
