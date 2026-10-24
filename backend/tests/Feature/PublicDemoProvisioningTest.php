<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 3).'/scripts/public-demo-security.php';

final class ProvisioningSetting extends \PDOStatement
{
    public function __construct(private bool $enabled) {}
    public function fetchColumn(int $column = 0): mixed { return $this->enabled ? 1 : 0; }
}

final class ProvisioningConnection extends \PDO
{
    public array $statements = [];
    public array $failOn = [];
    public bool $partialRevokes = false;
    public function __construct() {}
    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false { return "'".str_replace("'", "''", $string)."'"; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false { return new ProvisioningSetting($this->partialRevokes); }
    public function exec(string $statement): int|false
    {
        $this->statements[] = $statement;
        foreach ($this->failOn as $prefix) {
            if (str_starts_with($statement, $prefix)) throw new \RuntimeException('Synthetic database failure.');
        }
        return 0;
    }
}

class PublicDemoProvisioningTest extends TestCase
{
    private const DATABASE = 'clsu_public_demo_012345abcdef';
    private const USERNAME = 'portal_012345abcdef';

    public function test_runtime_has_only_dml_privileges_and_publishes_after_initialization(): void
    {
        $connection = new ProvisioningConnection();
        $steps = [];
        (new \PublicDemoProvisioner($connection))->provision(self::DATABASE, self::USERNAME, 'synthetic-password',
            function () use (&$steps) { $steps[] = 'initialize'; return ['synthetic-account']; },
            function ($accounts) use (&$steps) { $this->assertSame(['synthetic-account'], $accounts); $steps[] = 'publish'; },
            function () { $this->fail('Successful initialization must not trigger cleanup.'); });
        $this->assertSame(['initialize', 'publish'], $steps);
        $this->assertCount(3, $connection->statements);
        $this->assertStringStartsWith('GRANT SELECT, INSERT, UPDATE, DELETE ON `'.str_replace('_', '\\_', self::DATABASE).'`.*', $connection->statements[2]);
        $this->assertStringNotContainsString('ALL PRIVILEGES', implode("\n", $connection->statements));
    }

    public function test_failures_clean_only_resources_created_by_the_current_attempt(): void
    {
        foreach (['CREATE DATABASE', 'CREATE USER', 'GRANT SELECT', 'initialize', 'publish'] as $failure) {
            $connection = new ProvisioningConnection();
            $connection->failOn = [$failure];
            $removedFiles = false;
            try {
                (new \PublicDemoProvisioner($connection))->provision(self::DATABASE, self::USERNAME, 'synthetic-password',
                    function () use ($failure) { if ($failure === 'initialize') throw new \RuntimeException('synthetic secret'); return []; },
                    function () use ($failure) { if ($failure === 'publish') throw new \RuntimeException('synthetic secret'); },
                    function () use (&$removedFiles) { $removedFiles = true; });
                $this->fail('Expected provisioning failure.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('resources created by this attempt were removed', $error->getMessage());
                $this->assertStringNotContainsString('synthetic secret', $error->getMessage());
            }
            $this->assertTrue($removedFiles);
            $drops = array_values(array_filter($connection->statements, fn ($sql) => str_starts_with($sql, 'DROP ')));
            $expected = $failure === 'CREATE DATABASE' ? [] : ($failure === 'CREATE USER' ? ['DROP DATABASE `'.self::DATABASE.'`'] : ["DROP USER '".self::USERNAME."'@'127.0.0.1'", 'DROP DATABASE `'.self::DATABASE.'`']);
            $this->assertSame($expected, $drops);
        }
    }

    public function test_partial_revokes_mode_uses_literal_database_names(): void
    {
        $connection = new ProvisioningConnection();
        $connection->partialRevokes = true;
        (new \PublicDemoProvisioner($connection))->provision(self::DATABASE, self::USERNAME, 'synthetic-password', fn () => [], fn () => null, fn () => null);
        $this->assertStringStartsWith('GRANT SELECT, INSERT, UPDATE, DELETE ON `'.self::DATABASE.'`.*', $connection->statements[2]);
    }

    public function test_mysql_runtime_grants_are_exact_and_failed_initialization_removes_resources(): void
    {
        if (getenv('PORTAL_TEST_PROVISIONING') !== '1') {
            $this->markTestSkipped('Requires the explicitly enabled isolated MySQL provisioning test.');
        }
        $config = ['host' => getenv('DB_HOST'), 'port' => getenv('DB_PORT'), 'database' => getenv('DB_DATABASE')];
        \Tests\Support\TestEnvironmentGuard::assertSafe($_ENV['APP_ENV'] ?? '', false, (string) getenv('DB_CONNECTION'), $config);
        $operator = getenv('PORTAL_TEST_MYSQL_ADMIN_USERNAME');
        $password = getenv('PORTAL_TEST_MYSQL_ADMIN_PASSWORD');
        $this->assertNotFalse($operator, 'Explicit isolated-test operator credentials are required.');
        $admin = new \PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $operator, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $suffix = bin2hex(random_bytes(6));
        $database = 'clsu_public_demo_'.$suffix;
        $username = 'portal_'.$suffix;
        $runtimePassword = bin2hex(random_bytes(24));
        $container = getenv('PORTAL_TEST_MYSQL_CONTAINER');
        // A similarly named database must not match underscore wildcard grants.
        $neighbor = 'clsuXpublicXdemoX'.$suffix;
        $createdNeighbor = false;
        $verified = false;
        try {
            $admin->exec('CREATE DATABASE `'.$neighbor.'`');
            $createdNeighbor = true;
            $admin->exec('CREATE TABLE `'.$neighbor.'`.`synthetic_probe` (id INT PRIMARY KEY)');
            try {
                (new \PublicDemoProvisioner($admin))->provision($database, $username, $runtimePassword,
                    function () use ($admin, $database, $username, $runtimePassword, $neighbor, $container, &$verified) {
                        $admin->exec('CREATE TABLE `'.$database.'`.`synthetic_probe` (id INT PRIMARY KEY)');
                        if ($container) {
                            // Docker bridge connections do not authenticate as user@127.0.0.1.
                            // Exercise the actual loopback-only runtime account inside the service.
                            $execute = function (string $sql) use ($container, $database, $username, $runtimePassword): array {
                                $process = new Process(['docker', 'exec', '--env', 'MYSQL_PWD', $container, 'mysql', '--no-defaults', '--protocol=TCP', '--host=127.0.0.1', '--user='.$username, '--database='.$database, '--batch', '--skip-column-names', '--execute', $sql], null, ['MYSQL_PWD' => $runtimePassword]);
                                $process->run();
                                return [$process->isSuccessful(), $process->getOutput(), $process->getErrorOutput()];
                            };
                        } else {
                            $runtime = new \PDO('mysql:host=127.0.0.1;port=3307;dbname='.$database, $username, $runtimePassword, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                            $execute = function (string $sql) use ($runtime): array {
                                try {
                                    $statement = $runtime->query($sql);
                                    return [true, $statement->columnCount() > 0 ? (string) $statement->fetchColumn() : '', ''];
                                }
                                catch (\PDOException $error) { return [false, '', $error->getCode()]; }
                            };
                        }
                        $this->assertTrue($execute('INSERT INTO synthetic_probe VALUES (1)')[0]);
                        [$success, $count] = $execute('SELECT COUNT(*) FROM synthetic_probe');
                        $this->assertTrue($success);
                        $this->assertSame(1, (int) $count);
                        $this->assertTrue($execute('UPDATE synthetic_probe SET id=2')[0]);
                        $this->assertTrue($execute('DELETE FROM synthetic_probe')[0]);
                        foreach (['CREATE TABLE forbidden (id INT)', 'SELECT * FROM `'.$neighbor.'`.synthetic_probe'] as $sql) {
                            [$success, , $error] = $execute($sql);
                            $this->assertFalse($success, 'Runtime grant exceeded its intended scope.');
                            $this->assertStringContainsString('42000', $error);
                        }
                        $verified = true;
                        throw new \RuntimeException('Intentional isolated provisioning rollback.');
                    }, fn () => $this->fail('Failed initialization must not publish credentials.'), fn () => null);
                $this->fail('Expected intentional provisioning failure.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('resources created by this attempt were removed', $error->getMessage());
            }
            $this->assertTrue($verified, 'Every live MySQL grant/denial assertion must complete before intentional rollback.');
            $query = $admin->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
            $query->execute([$database]);
            $this->assertSame(0, (int) $query->fetchColumn());
            $query = $admin->prepare("SELECT COUNT(*) FROM mysql.user WHERE User=? AND Host='127.0.0.1'");
            $query->execute([$username]);
            $this->assertSame(0, (int) $query->fetchColumn());
            $this->assertSame(0, (int) $admin->query('SELECT COUNT(*) FROM `'.$neighbor.'`.synthetic_probe')->fetchColumn());
        } finally {
            // Remove only this test's successfully created neighbor; provisioning owns its own cleanup.
            if ($createdNeighbor) $admin->exec('DROP DATABASE `'.$neighbor.'`');
        }
    }

    public function test_cleanup_failure_is_reported_and_remaining_cleanup_is_still_attempted(): void
    {
        $connection = new ProvisioningConnection();
        $connection->failOn = ['DROP USER'];
        try {
            (new \PublicDemoProvisioner($connection))->provision(self::DATABASE, self::USERNAME, 'synthetic-password',
                fn () => throw new \RuntimeException('synthetic initialization failure'), fn () => null,
                fn () => throw new \RuntimeException('synthetic file cleanup failure'));
            $this->fail('Expected cleanup failure.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('cleanup incomplete', $error->getMessage());
        }
        $this->assertSame('DROP DATABASE `'.self::DATABASE.'`', end($connection->statements));
    }

    public function test_unexpected_resource_names_are_rejected_before_database_access(): void
    {
        $connection = new ProvisioningConnection();
        foreach ([['clsu_feedback', self::USERNAME], [self::DATABASE, 'root']] as [$database, $username]) {
            try {
                (new \PublicDemoProvisioner($connection))->provision($database, $username, 'synthetic-password', fn () => [], fn () => null, fn () => null);
                $this->fail('Unexpected resource names accepted.');
            } catch (\RuntimeException $error) {
                $this->assertSame('Refusing unexpected public-demo resource names.', $error->getMessage());
            }
        }
        $this->assertSame([], $connection->statements);
    }

    public function test_private_json_publication_is_complete_and_preserves_existing_file_on_encoding_failure(): void
    {
        $directory = dirname(__DIR__, 3).'/.local/secret-file-test-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        try {
            \PublicDemoFiles::protect($directory);
            $path = $directory.'/runtime.json';
            \PublicDemoFiles::writeJson($path, ['synthetic' => 'first']);
            \PublicDemoFiles::writeJson($path, ['synthetic' => 'second']);
            $this->assertSame(['synthetic' => 'second'], json_decode(file_get_contents($path), true));
            try {
                \PublicDemoFiles::writeJson($path, ['invalid' => "\xB1\x31"]);
                $this->fail('Invalid encoding must fail.');
            } catch (\JsonException) {}
            $this->assertSame(['synthetic' => 'second'], json_decode(file_get_contents($path), true));
            $this->assertSame([], glob($directory.'/.secret-*'));
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->assertSame(0600, fileperms($path) & 0777);
                $this->assertSame(0700, fileperms($directory) & 0777);
            }
        } finally {
            if (is_file($directory.'/runtime.json')) unlink($directory.'/runtime.json');
            rmdir($directory);
        }
    }
}
