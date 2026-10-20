<?php
// Provisions a fresh synthetic database, never copying or resetting local records.
require dirname(__DIR__).'/backend/vendor/autoload.php';
$root = dirname(__DIR__);
$directory = $root.'/.local/public-host';
$url = $argv[1] ?? '';
if (!preg_match('~^https://[a-z0-9]+(?:-[a-z0-9]+)+\.trycloudflare\.com$~', $url)) throw new RuntimeException('Expected an HTTPS quick-tunnel URL.');
foreach (['sessions', 'cache', 'private', 'logs', 'framework/views'] as $path) {
    if (!is_dir($directory.'/'.$path)) mkdir($directory.'/'.$path, 0700, true);
}
if (file_exists($directory.'/runtime.json')) {
    $runtime = json_decode(file_get_contents($directory.'/runtime.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($runtime['APP_ENV'] ?? '') !== 'production' || !str_starts_with($runtime['DB_DATABASE'] ?? '', 'clsu_public_demo_')) throw new RuntimeException('Unexpected public-demo configuration.');
    $runtime['APP_URL'] = $url;
    file_put_contents($directory.'/runtime.json', json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Existing public demo retained; origin updated.\n";
    exit;
}
$local = Dotenv\Dotenv::parse(file_get_contents($root.'/backend/.env'));
if (($local['DB_HOST'] ?? '') !== '127.0.0.1' || ($local['DB_PORT'] ?? '') !== '3307') throw new RuntimeException('Provisioning requires the project-owned loopback MySQL instance on port 3307.');
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $local['DB_USERNAME'], $local['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$suffix = bin2hex(random_bytes(6));
$database = 'clsu_public_demo_'.$suffix;
$username = 'portal_'.$suffix;
$password = bin2hex(random_bytes(24));
$runtime = [
    'APP_ENV'=>'production', 'APP_DEBUG'=>'false', 'APP_URL'=>$url,
    'APP_KEY'=>'base64:'.base64_encode(random_bytes(32)),
    'APP_CONFIG_CACHE'=>$directory.'/config-cache.php',
    'DB_CONNECTION'=>'mysql', 'DB_HOST'=>'127.0.0.1', 'DB_PORT'=>'3307',
    'DB_DATABASE'=>$database, 'DB_USERNAME'=>$username, 'DB_PASSWORD'=>$password,
    'SESSION_DRIVER'=>'file', 'SESSION_SECURE_COOKIE'=>'true', 'SESSION_ENCRYPT'=>'true',
    'SESSION_COOKIE'=>'clsu_public_demo', 'SESSION_FILES_PATH'=>$directory.'/sessions',
    'CACHE_STORE'=>'file', 'CACHE_FILE_PATH'=>$directory.'/cache', 'CACHE_PREFIX'=>'public_demo_'.$suffix,
    'PRIVATE_STORAGE_PATH'=>$directory.'/private', 'LARAVEL_STORAGE_PATH'=>$directory,
    'QUEUE_CONNECTION'=>'database', 'LOG_CHANNEL'=>'stderr', 'LOG_LEVEL'=>'warning',
    'DEMO_SEED'=>'false', 'PORTAL_TRUSTED_ORIGINS'=>'', 'PORTAL_TRUSTED_PROXIES'=>'127.0.0.1,::1',
];
$pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('CREATE USER '.$pdo->quote($username)."@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password));
$pdo->exec('GRANT ALL PRIVILEGES ON `'.$database.'`.* TO '.$pdo->quote($username)."@'127.0.0.1'");
// Seed only the new database in a private initialization process; public runtime stays production.
$initial = [...$runtime, 'APP_ENV'=>'local', 'DEMO_SEED'=>'true'];
foreach ($initial as $name=>$value) { $_ENV[$name]=$_SERVER[$name]=$value; putenv($name.'='.$value); }
$app = require $root.'/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if ($kernel->call('migrate', ['--force'=>true]) !== 0) throw new RuntimeException('Public-demo migrations failed.');
if ($kernel->call('db:seed', ['--force'=>true]) !== 0) throw new RuntimeException('Public-demo synthetic seeding failed.');
$accounts=[];
foreach (App\Models\User::all() as $user) {
    $accountPassword = bin2hex(random_bytes(12));
    $user->forceFill(['password'=>$accountPassword, 'remember_token'=>null])->save();
    $accounts[] = ['role'=>$user->role, 'email'=>$user->email, 'password'=>$accountPassword];
}
$pdo->exec('REVOKE ALL PRIVILEGES ON `'.$database.'`.* FROM '.$pdo->quote($username)."@'127.0.0.1'");
$pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$database.'`.* TO '.$pdo->quote($username)."@'127.0.0.1'");
file_put_contents($directory.'/access.json', json_encode($accounts, JSON_PRETTY_PRINT));
file_put_contents($directory.'/runtime.json', json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Created a separate synthetic public-demo database; account passwords saved privately in .local/public-host/access.json.\n";
