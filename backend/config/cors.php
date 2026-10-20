<?php
$origins=[env('APP_URL','http://127.0.0.1:5173'),...explode(',',env('PORTAL_TRUSTED_ORIGINS',''))];
$normalize=static function($value){
 $parts=parse_url(trim($value));
 if(!$parts||!isset($parts['scheme'],$parts['host'])||str_contains($parts['host'],'*')||!in_array(strtolower($parts['scheme']),['http','https'],true)||isset($parts['user'])||isset($parts['pass']))return null;
 $scheme=strtolower($parts['scheme']);$port=$parts['port']??($scheme==='https'?443:80);
 return $scheme.'://'.strtolower($parts['host']).(($scheme==='https'&&$port===443)||($scheme==='http'&&$port===80)?'':':'.$port);
};
return [
 'paths'=>['api/*'],
 'allowed_methods'=>['GET','HEAD','POST','PATCH','OPTIONS'],
 'allowed_origins'=>array_values(array_unique(array_filter(array_map($normalize,$origins)))),
 'allowed_origins_patterns'=>[],
 'allowed_headers'=>['Accept','Content-Type'],
 'exposed_headers'=>[],
 'max_age'=>600,
 'supports_credentials'=>false,
];
