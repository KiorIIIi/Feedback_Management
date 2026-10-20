param([string]$PhpPath='C:\laragon\bin\php\php-8.5.10-Win32-vs17-x64\php.exe',[switch]$SkipBuild,[switch]$UseWindowsDns)
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$projectRoot=Split-Path -Parent $PSScriptRoot
$runtimeDir=Join-Path $projectRoot '.local/public-host'
$tunnelPath=Join-Path $projectRoot '.local/tools/cloudflared.exe'
foreach($file in @($PhpPath,$tunnelPath,(Join-Path $projectRoot 'backend/vendor/autoload.php'))){
 if(-not (Test-Path -LiteralPath $file -PathType Leaf)){throw "Required file is missing: $file"}
}
New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
foreach($name in @('web','worker','tunnel')){
 $savedId=Get-LocalRuntimePid (Join-Path $runtimeDir ($name+'.pid'))
 if($savedId -and (Get-Process -Id $savedId -ErrorAction SilentlyContinue)){throw 'A public-host process is already running. Use stop-public.ps1 before restarting.'}
}
if(@(Get-NetTCPConnection -LocalPort 4180 -State Listen -ErrorAction SilentlyContinue).Count){throw 'Port 4180 is already occupied. No existing process was stopped.'}
& (Join-Path $PSScriptRoot 'init-local-db.ps1')
if(-not $SkipBuild){
 & npm.cmd --prefix (Join-Path $projectRoot 'frontend') run build:hosted-demo
 if($LASTEXITCODE -ne 0){throw 'Frontend build failed.'}
}
$dist=Join-Path $projectRoot 'frontend/dist'
if(-not (Test-Path -LiteralPath (Join-Path $dist 'index.html'))){throw 'The frontend build is missing.'}
$site=Join-Path $runtimeDir 'site'
New-Item -ItemType Directory -Path $site -Force | Out-Null
Get-ChildItem -LiteralPath $dist | Copy-Item -Destination $site -Recurse -Force
try {
 $tunnelArgs=@('tunnel','--no-autoupdate','--protocol','http2','--url','http://127.0.0.1:4180')
 if($UseWindowsDns){
  # Version-specific fallback: resolve only official Cloudflare edge addresses.
  # TLS verification stays enabled; no OS DNS or firewall settings are changed.
  foreach($region in @('region1.v2.argotunnel.com','region2.v2.argotunnel.com')){
   $edgeAddress=Resolve-DnsName $region -Type A -ErrorAction Stop | Where-Object Type -eq A | Select-Object -First 1 -ExpandProperty IPAddress
   if(-not $edgeAddress){throw "No Cloudflare edge address was returned for $region."}
   $tunnelArgs+=@('--edge',($edgeAddress+':7844'))
  }
  $tunnelArgs+='--no-prechecks'
 }
 $tunnel=Start-Process -FilePath $tunnelPath -ArgumentList $tunnelArgs -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDir 'tunnel.out.log') -RedirectStandardError (Join-Path $runtimeDir 'tunnel.err.log')
 $tunnel.Id | Set-Content (Join-Path $runtimeDir 'tunnel.pid')
 $publicUrl=$null
 for($attempt=0;$attempt -lt 60;$attempt++){
  $log=Get-Content -LiteralPath (Join-Path $runtimeDir 'tunnel.err.log') -Raw -ErrorAction SilentlyContinue
  if($tunnel.HasExited){throw 'Cloudflare Tunnel exited. Check .local/public-host/tunnel.err.log.'}
  if($log -match 'Your quick Tunnel has been created' -and $log -match 'https://[a-z0-9]+(?:-[a-z0-9]+)+\.trycloudflare\.com'){$publicUrl=$Matches[0];break}
  Start-Sleep -Milliseconds 500
 }
 if(-not $publicUrl){throw 'Cloudflare did not return a public URL in time.'}
 & $PhpPath -d zend.exception_ignore_args=1 (Join-Path $PSScriptRoot 'init-public-demo.php') $publicUrl
 if($LASTEXITCODE -ne 0){throw 'Public-demo initialization failed.'}
 $web=Start-Process -FilePath $PhpPath -ArgumentList @('-d','display_errors=0','-d','zend.exception_ignore_args=1','-S','127.0.0.1:4180','-t',"`"$site`"","`"$(Join-Path $PSScriptRoot 'public-router.php')`"") -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDir 'web.out.log') -RedirectStandardError (Join-Path $runtimeDir 'web.err.log')
 $web.Id | Set-Content (Join-Path $runtimeDir 'web.pid')
 Wait-LocalRuntimeHttp -Uri 'http://127.0.0.1:4180/up' -Name 'Public demo web process'
 $worker=Start-Process -FilePath $PhpPath -ArgumentList @('-d','display_errors=0',"`"$(Join-Path $PSScriptRoot 'public-worker.php')`"") -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDir 'worker.out.log') -RedirectStandardError (Join-Path $runtimeDir 'worker.err.log')
 $worker.Id | Set-Content (Join-Path $runtimeDir 'worker.pid')
 $reachable=$false
 $healthFailure='No public response received.'
 for($attempt=0;$attempt -lt 60;$attempt++){
  if($tunnel.HasExited -or $worker.HasExited){throw 'The public tunnel or queue worker exited before verification.'}
  try {
   $response=Invoke-WebRequest -Uri ($publicUrl+'/up') -UseBasicParsing -TimeoutSec 3
   if($response.StatusCode -eq 200){$reachable=$true;break}
  }catch{$healthFailure=$_.Exception.Message}
  Start-Sleep -Milliseconds 1000
 }
 if(-not $reachable){throw "The public HTTPS health check did not succeed: $healthFailure The public processes will be stopped."}
 Set-Content -LiteralPath (Join-Path $runtimeDir 'url.txt') -Value $publicUrl
 Write-Output "Public demo: $publicUrl"
 Write-Output 'Credentials: .local/public-host/access.json (keep private). Stop with scripts/stop-public.ps1.'
} catch {
 & (Join-Path $PSScriptRoot 'stop-public.ps1') -PhpPath $PhpPath
 throw
}
