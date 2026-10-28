<?php
// This router serves the built SPA only; source code and .local are outside its document root.
$runtime = require __DIR__.'/public-runtime.php';
$expectedHost = parse_url($runtime['APP_URL'], PHP_URL_HOST);
$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
if (!in_array($host, [$expectedHost, '127.0.0.1', 'localhost'], true)) {
    http_response_code(400);
    exit('Invalid host.');
}
if ($host === $expectedHost && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https') {
    header('Location: '.$runtime['APP_URL'].($_SERVER['REQUEST_URI'] ?? '/'), true, 308);
    exit;
}
// The connector reaches this loopback-only server. Discard caller-supplied XFF;
// Cloudflare replaces CF-Connecting-IP at its edge.
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '127.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : '127.0.0.1';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$oauthMetadata = in_array($path, ['/.well-known/oauth-authorization-server', '/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/api/mcp', '/.well-known/oauth-authorization-server/api/mcp'], true);
if (str_contains($path, "\0") || str_contains($path, '\\') || (!$oauthMetadata && preg_match('~(?:^|/)\.~', $path))) {
    http_response_code(404);
    exit('Not found.');
}
if ($path === '/up' || $path === '/api' || str_starts_with($path, '/api/') || str_starts_with($path, '/oauth/') || str_starts_with($path, '/.well-known/')) {
    require dirname(__DIR__).'/backend/public/index.php';
    return;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'");
header('Strict-Transport-Security: max-age=31536000');
$site = realpath(dirname(__DIR__).'/.local/public-host/site');
$file = realpath($site.$path);
if (!$file || !is_file($file) || !str_starts_with($file, $site.DIRECTORY_SEPARATOR)) {
    if (pathinfo($path, PATHINFO_EXTENSION)) {
        http_response_code(404);
        exit('Not found.');
    }
    $file = $site.'/index.html';
}
$types = ['html'=>'text/html; charset=utf-8', 'js'=>'text/javascript; charset=utf-8', 'css'=>'text/css; charset=utf-8', 'svg'=>'image/svg+xml', 'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'ico'=>'image/x-icon', 'woff'=>'font/woff', 'woff2'=>'font/woff2', 'webp'=>'image/webp'];
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!isset($types[$extension])) {
    http_response_code(404);
    exit('Not found.');
}
header('Content-Type: '.$types[$extension]);
header('Cache-Control: '.($extension === 'html' ? 'no-store' : 'public, max-age=3600'));
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($file);
