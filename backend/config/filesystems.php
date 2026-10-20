<?php
return ['default'=>'local','disks'=>['local'=>['driver'=>'local','root'=>env('PRIVATE_STORAGE_PATH',storage_path('app/private')),'throw'=>true]]];
