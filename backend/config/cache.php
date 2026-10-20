<?php
return ['default'=>env('CACHE_STORE','file'),'stores'=>['file'=>['driver'=>'file','path'=>env('CACHE_FILE_PATH',storage_path('framework/cache/data'))],'array'=>['driver'=>'array','serialize'=>false]],'prefix'=>env('CACHE_PREFIX','clsu_portal')];
