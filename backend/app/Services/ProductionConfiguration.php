<?php
namespace App\Services;

final class ProductionConfiguration {
 public static function validProxy(string $proxy):bool {
  $parts=explode('/',$proxy);
  if(!filter_var($parts[0],FILTER_VALIDATE_IP)||count($parts)>2)return false;
  return count($parts)===1||(ctype_digit($parts[1])&&(int)$parts[1]>0&&(int)$parts[1]<=(str_contains($parts[0],':')?128:32));
 }
 public static function assertSafe(array $settings):void {
  if(in_array($settings['app']['env']??null,['local','testing'],true))return;
  $failures=[];$app=$settings['app']??[];$session=$settings['session']??[];
  if($app['debug']??true)$failures[]='APP_DEBUG';
  $url=parse_url($app['url']??'');
  if(!$url||!filter_var($app['url']??'',FILTER_VALIDATE_URL)||($url['scheme']??null)!=='https'||empty($url['host'])||str_contains($url['host'],'*')||isset($url['user'])||isset($url['pass']))$failures[]='APP_URL';
  if(empty($session['secure']))$failures[]='SESSION_SECURE_COOKIE';
  if(empty($session['encrypt']))$failures[]='SESSION_ENCRYPT';
  $key=$app['key']??'';$decoded=str_starts_with($key,'base64:')?base64_decode(substr($key,7),true):$key;
  if(!is_string($decoded)||strlen($decoded)!==32||$key==='base64:ERERERERERERERERERERERERERERERERERERERERERE=')$failures[]='APP_KEY';
  $db=$settings['database']??[];$connection=$db['connections'][$db['default']??'']??[];
  if(($db['default']??null)!=='mysql'||empty($connection['database'])||str_ends_with($connection['database'],'_test')||empty($connection['username'])||strtolower($connection['username'])==='root'||strlen(trim((string)($connection['password']??'')))<12||in_array(strtolower($connection['password']??''),['demopass!2026','changeme123456','password123456'],true))$failures[]='database credentials';
  foreach($settings['security']['trusted_origins']??[] as $origin){$parts=parse_url(trim($origin));if(!$parts||!filter_var(trim($origin),FILTER_VALIDATE_URL)||($parts['scheme']??null)!=='https'||empty($parts['host'])||str_contains($parts['host'],'*')||isset($parts['user'])||isset($parts['pass']))$failures[]='PORTAL_TRUSTED_ORIGINS';}
  foreach($settings['security']['trusted_proxies']??[] as $proxy)if(!self::validProxy($proxy))$failures[]='PORTAL_TRUSTED_PROXIES';
  if($settings['demo_seed']??false)$failures[]='DEMO_SEED';
  if(!is_bool($settings['mcp']['enabled']??false))$failures[]='MCP_ENABLED';
  if($failures)throw new \RuntimeException('Unsafe production configuration: '.implode(', ',array_unique($failures)).'.');
 }
}
