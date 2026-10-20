<?php
// Shared by the public web process and queue worker. Secrets remain in .local.
$publicRoot = dirname(__DIR__).'/.local/public-host';
$runtime = json_decode(file_get_contents($publicRoot.'/runtime.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($runtime as $name => $value) {
    $_ENV[$name] = $_SERVER[$name] = (string) $value;
    putenv($name.'='.$value);
}
return $runtime;
