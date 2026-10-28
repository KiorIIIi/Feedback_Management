<?php
return [
 'demo_seed'=>filter_var(env('DEMO_SEED',false),FILTER_VALIDATE_BOOL),
 'trusted_origins'=>array_filter(explode(',',env('PORTAL_TRUSTED_ORIGINS',''))),
 'trusted_proxies'=>array_values(array_filter(array_map('trim',explode(',',env('PORTAL_TRUSTED_PROXIES',''))))),
 'login_ip_per_minute'=>env('APP_ENV')==='testing'?(int)env('PORTAL_TEST_LOGIN_LIMIT',10):10,
 'login_account_per_minute'=>env('APP_ENV')==='testing'?(int)env('PORTAL_TEST_LOGIN_LIMIT',10):10,
 'feedback_per_minute'=>10,
 'report_per_minute'=>5,
 'active_reports_per_user'=>2,
 'mutations_per_minute'=>60,
 'reads_per_minute'=>env('APP_ENV')==='testing'?(int)env('PORTAL_TEST_READ_LIMIT',240):240,
];
