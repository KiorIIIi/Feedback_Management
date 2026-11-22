<?php
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::prefix('api')->group(function(){
 Route::post('login',[PortalController::class,'login'])->middleware('throttle:portal-login');
 Route::post('feedback',[PortalController::class,'feedback'])->middleware('throttle:portal-feedback');
 // Public metadata for a shareable event form; the token is unguessable and the
 // payload exposes only this form's public fields.
 Route::get('feedback-forms/{token}',[PortalController::class,'publicForm'])->where('token','[A-Za-z0-9]+')->middleware('throttle:portal-feedback');
 Route::middleware(['auth','throttle:portal-reads'])->group(function(){
  Route::get('assistant/settings',[\App\Http\Controllers\AssistantController::class,'status']);
  // Key listing is a read: it stays under the portal-reads budget (safe methods)
  // instead of the assistant-admin write limiter. The unified assistant page
  // refetches this list on every visit and after each settings save, so
  // counting it against the 6/min write budget starved ordinary admin use.
  Route::get('assistant/keys',[\App\Http\Controllers\AssistantController::class,'keys']);
  Route::middleware('throttle:assistant-admin')->group(function(){
   Route::post('assistant/keys',[\App\Http\Controllers\AssistantController::class,'createKey']);
   Route::delete('assistant/keys/{id}',[\App\Http\Controllers\AssistantController::class,'revokeKey'])->whereNumber('id');
   Route::put('assistant/settings',[\App\Http\Controllers\AssistantController::class,'save']);
   Route::post('assistant/clients',[\App\Http\Controllers\AssistantController::class,'createClient']);
   Route::delete('assistant/clients/{id}',[\App\Http\Controllers\AssistantController::class,'revokeClient']);
   Route::post('assistant/test',[\App\Http\Controllers\AssistantController::class,'test']);
  });
  Route::post('assistant/chat',[\App\Http\Controllers\AssistantController::class,'chat'])->middleware('throttle:assistant-chat');
  Route::post('assistant/local-read',[\App\Http\Controllers\AssistantController::class,'localRead'])->middleware('throttle:assistant-chat');
  Route::get('me',fn(Request $r)=>$r->user());Route::post('logout',[PortalController::class,'logout']);
  Route::get('catalog',[PortalController::class,'catalog']);
  Route::post('request-categories',[PortalController::class,'createCategory'])->middleware('throttle:portal-mutations');
  Route::get('report-summary',[ReportController::class,'summary']);
  Route::get('requester-dashboard',fn(Request $request,\App\Services\RequesterDashboardService $dashboard)=>$dashboard->get($request->user()));
  Route::get('requests',[PortalController::class,'index']);Route::post('requests',[PortalController::class,'store'])->middleware('throttle:30,1');
  Route::get('requests/{id}',[PortalController::class,'show'])->whereNumber('id');Route::patch('requests/{id}',[PortalController::class,'update'])->whereNumber('id')->middleware('throttle:portal-mutations');
  Route::post('requests/{id}/evaluation',[PortalController::class,'evaluate'])->whereNumber('id')->middleware('throttle:portal-mutations');
  Route::get('feedback',[PortalController::class,'feedbackIndex']);Route::get('metrics',[PortalController::class,'metrics']);
  Route::get('feedback-forms',[PortalController::class,'formsIndex']);
  Route::post('feedback-forms',[PortalController::class,'formsCreate'])->middleware('throttle:portal-mutations');
  Route::patch('feedback-forms/{id}',[PortalController::class,'formsUpdate'])->whereNumber('id')->middleware('throttle:portal-mutations');
  Route::get('feedback/{kind}/{id}',[PortalController::class,'feedbackShow'])->whereIn('kind',['general','evaluation'])->whereNumber('id');
  Route::get('notifications',[PortalController::class,'notifications']);Route::post('notifications/{id}/read',[PortalController::class,'readNotification'])->whereNumber('id')->middleware('throttle:portal-mutations');
  Route::post('reports',[ReportController::class,'store'])->middleware('throttle:portal-reports');Route::get('reports/{id}',[ReportController::class,'show'])->whereNumber('id');Route::get('reports/{id}/download',[ReportController::class,'download'])->whereNumber('id')->middleware('throttle:portal-downloads');
 });
});
