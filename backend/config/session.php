<?php
$production=!in_array(env('APP_ENV','local'),['local','testing'],true);
return ['driver'=>env('SESSION_DRIVER','file'),'lifetime'=>120,'expire_on_close'=>false,'encrypt'=>env('SESSION_ENCRYPT',$production),'files'=>env('SESSION_FILES_PATH',storage_path('framework/sessions')),'connection'=>null,'table'=>'sessions','store'=>null,'lottery'=>[2,100],'cookie'=>env('SESSION_COOKIE','clsu_portal_session'),'path'=>'/','domain'=>null,'secure'=>env('SESSION_SECURE_COOKIE',$production),'http_only'=>true,'same_site'=>'lax','partitioned'=>false];
