<?php
return ['default'=>env('QUEUE_CONNECTION','database'),'connections'=>['sync'=>['driver'=>'sync'],'database'=>['driver'=>'database','connection'=>null,'table'=>'jobs','queue'=>'default','retry_after'=>120,'after_commit'=>true]],'failed'=>['driver'=>'database-uuids','database'=>env('DB_CONNECTION','mysql'),'table'=>'failed_jobs']];
