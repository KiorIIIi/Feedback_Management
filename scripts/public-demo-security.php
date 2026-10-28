<?php

// CLI-only helpers. No application route exposes provisioning or secret administration.
final class PublicDemoFiles
{
    public static function protect(string $directory): void
    {
        $local = dirname(__DIR__).'/.local';
        $resolved = realpath($directory);
        $root = realpath($local);
        $prefix = $root === false ? '' : $root.DIRECTORY_SEPARATOR;
        $inside = $resolved !== false && $root !== false && (PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($resolved), strtolower($prefix)) : str_starts_with($resolved, $prefix));
        if (!$inside) {
            throw new RuntimeException('Secret directory must be inside project-local runtime storage.');
        }
        for ($cursor = $directory; $cursor !== dirname($cursor); $cursor = dirname($cursor)) {
            if (is_link($cursor)) throw new RuntimeException('Runtime paths must not contain links.');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $process = proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'RemoteSigned', '-File', __DIR__.'/protect-public-secrets.ps1', '-Directory', $directory],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($process)) throw new RuntimeException('Cannot protect public-demo secrets.');
            fclose($pipes[0]);
            stream_get_contents($pipes[1]); fclose($pipes[1]);
            stream_get_contents($pipes[2]); fclose($pipes[2]);
            if (proc_close($process) !== 0) throw new RuntimeException('Cannot restrict public-demo secret ACLs.');
        } else {
            if (!chmod($directory, 0700)) throw new RuntimeException('Cannot restrict public-demo directory.');
            foreach (['runtime.json', 'access.json'] as $name) {
                $path = $directory.'/'.$name;
                if (is_link($path) || (file_exists($path) && (!is_file($path) || !chmod($path, 0600)))) {
                    throw new RuntimeException('Cannot restrict public-demo secret files.');
                }
            }
        }
    }

    public static function writeJson(string $path, array $data): void
    {
        if (!in_array(basename($path), ['runtime.json', 'access.json'], true) || is_link($path)) {
            throw new RuntimeException('Unexpected public-demo secret path.');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = dirname($path).'/.secret-'.bin2hex(random_bytes(12));
        $oldMask = umask(0077);
        try {
            $handle = fopen($temporary, 'xb');
        } finally {
            umask($oldMask);
        }
        if ($handle === false) throw new RuntimeException('Cannot create private secret file.');
        try {
            if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
                throw new RuntimeException('Cannot write complete public-demo configuration.');
            }
            fclose($handle);
            $handle = null;
            if (!rename($temporary, $path)) throw new RuntimeException('Cannot publish public-demo configuration.');
        } finally {
            if (is_resource($handle)) fclose($handle);
            if (is_file($temporary)) unlink($temporary);
        }
    }
}

final class PublicDemoProvisioner
{
    public function __construct(private PDO $pdo) {}

    public function provision(string $database, string $username, string $password, callable $initialize, callable $publish, callable $removeFiles): void
    {
        if (!preg_match('/^clsu_public_demo_([a-f0-9]{12})$/D', $database, $match) || $username !== 'portal_'.$match[1]) {
            throw new RuntimeException('Refusing unexpected public-demo resource names.');
        }
        $account = $this->pdo->quote($username)."@'127.0.0.1'";
        $createdDatabase = $createdUser = false;
        try {
            // Database-level GRANT treats underscores as wildcards unless partial_revokes is on.
            $partialRevokes = (bool) $this->pdo->query('SELECT @@GLOBAL.partial_revokes')->fetchColumn();
            $grantDatabase = $partialRevokes ? $database : str_replace('_', '\\_', $database);
            $this->pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdDatabase = true;
            $this->pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$this->pdo->quote($password));
            $createdUser = true;
            // Runtime never receives schema-management privileges, including during initialization.
            $this->pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$grantDatabase.'`.* TO '.$account);
            $accounts = $initialize();
            $publish($accounts);
        } catch (Throwable) {
            $cleanupFailed = false;
            try { $removeFiles(); } catch (Throwable) { $cleanupFailed = true; }
            // Ownership flags refer only to resources successfully created by this invocation.
            if ($createdUser) {
                try { $this->pdo->exec('DROP USER '.$account); } catch (Throwable) { $cleanupFailed = true; }
            }
            if ($createdDatabase) {
                try { $this->pdo->exec('DROP DATABASE `'.$database.'`'); } catch (Throwable) { $cleanupFailed = true; }
            }
            throw new RuntimeException($cleanupFailed
                ? 'Public-demo initialization failed; cleanup incomplete. A trusted operator must inspect newly created demo resources.'
                : 'Public-demo initialization failed; resources created by this attempt were removed.');
        }
    }
}
