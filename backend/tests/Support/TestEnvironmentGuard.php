<?php
namespace Tests\Support;

final class TestEnvironmentGuard {
 public static function assertSafe(string $environment,bool $cached,string $driver,array $connection):void {
  $sqlite=$driver==='sqlite'&&($connection['database']??null)===':memory:';
  $mysql=$driver==='mysql'&&($connection['database']??null)==='clsu_feedback_test'
   &&in_array($connection['host']??null,['127.0.0.1','localhost'],true)
   &&(int)($connection['port']??0)===3307
   &&empty($connection['url'])&&empty($connection['read'])&&empty($connection['write']);
  if($environment!=='testing'||$cached||(!$sqlite&&!$mysql)||!empty($connection['url']))
   throw new \RuntimeException('Refusing destructive tests. Disable cached configuration and use testing with SQLite :memory: or the local clsu_feedback_test database on port 3307.');
 }
}
