<?php
return ['default'=>env('DB_CONNECTION','mysql'),'connections'=>[
 'mysql'=>['driver'=>'mysql','host'=>env('DB_HOST','127.0.0.1'),'port'=>env('DB_PORT','3306'),'database'=>env('DB_DATABASE','clsu_feedback'),'username'=>env('DB_USERNAME','root'),'password'=>env('DB_PASSWORD',''),'unix_socket'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true,'engine'=>'InnoDB'],
 'sqlite'=>['driver'=>'sqlite','database'=>env('DB_DATABASE',database_path('database.sqlite')),'prefix'=>'','foreign_key_constraints'=>true,'busy_timeout'=>5000],
],'migrations'=>['table'=>'migrations','update_date_on_publish'=>true]];
