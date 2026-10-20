<?php
namespace Tests;
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase {
 public function createApplication(){
  $app=parent::createApplication();
  $connection=$app['config']->get('database.default');
  \Tests\Support\TestEnvironmentGuard::assertSafe($app->environment(),$app->configurationIsCached(),$connection,$app['config']->get('database.connections.'.$connection,[]));
  return $app;
 }
 protected function setUp():void {
  parent::setUp();
  \Illuminate\Support\Facades\Storage::fake('local');
  $this->withHeaders(['Origin'=>config('app.url')]);
 }
}
