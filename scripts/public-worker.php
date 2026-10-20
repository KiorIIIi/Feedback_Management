<?php
require __DIR__.'/public-runtime.php';
require dirname(__DIR__).'/backend/vendor/autoload.php';
$app = require dirname(__DIR__).'/backend/bootstrap/app.php';
exit($app->handleCommand(new Symfony\Component\Console\Input\ArrayInput([
    'command' => 'queue:work', '--sleep' => 1, '--tries' => 3, '--timeout' => 90,
])));
