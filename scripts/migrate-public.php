<?php
// Deployment-only command. Never exposed as an application or MCP operation.
$runtime = require __DIR__.'/public-runtime.php';
if (($runtime['DB_HOST'] ?? '') !== '127.0.0.1' || ($runtime['DB_PORT'] ?? '') !== '3307'
    || !preg_match('/^clsu_public_demo_[a-f0-9]+$/', $runtime['DB_DATABASE'] ?? '')) {
    throw new RuntimeException('Refusing migration outside the isolated public demo database.');
}
require dirname(__DIR__).'/backend/vendor/autoload.php';
$app = require dirname(__DIR__).'/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$local = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__).'/backend/.env'));
if (($local['DB_HOST'] ?? '') !== '127.0.0.1' || ($local['DB_PORT'] ?? '') !== '3307') {
    throw new RuntimeException('Expected the project-owned MySQL instance.');
}
// The app guard first validates the actual production runtime. Migration credentials
// are then used only by this CLI process and never saved to public runtime.json.
config(['database.connections.mysql.username'=>$local['DB_USERNAME'], 'database.connections.mysql.password'=>$local['DB_PASSWORD'] ?? '']);
Illuminate\Support\Facades\DB::purge('mysql');
$result = $kernel->call('migrate', ['--force'=>true]);
echo $kernel->output();
exit($result);
