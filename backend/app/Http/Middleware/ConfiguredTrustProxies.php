<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use App\Services\ProductionConfiguration;

class ConfiguredTrustProxies extends \Illuminate\Http\Middleware\TrustProxies {
 protected $headers=Request::HEADER_X_FORWARDED_FOR|Request::HEADER_X_FORWARDED_PROTO|Request::HEADER_X_FORWARDED_PORT;
 protected function setTrustedProxyIpAddresses(Request $request){
  $proxies=config('security.trusted_proxies',[]);
  foreach($proxies as $proxy)if(!ProductionConfiguration::validProxy($proxy))throw new \RuntimeException('Trusted proxies must be explicit IP addresses or CIDR ranges.');
  $request->setTrustedProxies($proxies,$this->getTrustedHeaderNames());
 }
}
