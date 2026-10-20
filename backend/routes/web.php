<?php
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::prefix('api')->group(function(){
 Route::post('login',[PortalController::class,'login'])->middleware('throttle:portal-login');
 Route::post('feedback',[PortalController::class,'feedback'])->middleware('throttle:portal-feedback');
 Route::middleware(['auth','throttle:portal-reads'])->group(function(){
  Route::get('me',fn(Request $r)=>$r->user());Route::post('logout',[PortalController::class,'logout']);
  Route::get('catalog',[PortalController::class,'catalog']);
  Route::post('request-categories',[PortalController::class,'createCategory'])->middleware('throttle:portal-mutations');
  Route::get('report-summary',[ReportController::class,'summary']);
  Route::get('requester-dashboard',fn(Request $request,\App\Services\RequesterDashboardService $dashboard)=>$dashboard->get($request->user()));
  Route::get('requests',[PortalController::class,'index']);Route::post('requests',[PortalController::class,'store'])->middleware('throttle:30,1');
  Route::get('requests/{id}',[PortalController::class,'show'])->whereNumber('id');Route::patch('requests/{id}',[PortalController::class,'update'])->whereNumber('id')->middleware('throttle:portal-mutations');
  Route::post('requests/{id}/evaluation',[PortalController::class,'evaluate'])->whereNumber('id')->middleware('throttle:portal-mutations');
  Route::get('feedback',[PortalController::class,'feedbackIndex']);Route::get('metrics',[PortalController::class,'metrics']);
  Route::get('feedback/{kind}/{id}',[PortalController::class,'feedbackShow'])->whereIn('kind',['general','evaluation'])->whereNumber('id');
  Route::get('notifications',[PortalController::class,'notifications']);Route::post('notifications/{id}/read',[PortalController::class,'readNotification'])->whereNumber('id')->middleware('throttle:portal-mutations');
  Route::post('reports',[ReportController::class,'store'])->middleware('throttle:portal-reports');Route::get('reports/{id}',[ReportController::class,'show'])->whereNumber('id');Route::get('reports/{id}/download',[ReportController::class,'download'])->whereNumber('id');
 });
});
