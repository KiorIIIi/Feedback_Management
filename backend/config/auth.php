<?php
return ['defaults'=>['guard'=>'web','passwords'=>'users'],'guards'=>['web'=>['driver'=>'session','provider'=>'users'],'mcp-oauth'=>['driver'=>'passport','provider'=>'oauth_users']],'providers'=>['users'=>['driver'=>'eloquent','model'=>App\Models\User::class],'oauth_users'=>['driver'=>'eloquent','model'=>App\Models\OAuthUser::class]]];
