<?php
$level=env('LOG_LEVEL',in_array(env('APP_ENV','local'),['local','testing'],true)?'debug':'warning');
return ['default'=>'single','channels'=>['single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>$level],'stderr'=>['driver'=>'monolog','level'=>$level,'handler'=>Monolog\Handler\StreamHandler::class,'with'=>['stream'=>'php://stderr']]]];
