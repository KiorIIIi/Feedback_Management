# Non-destructive regression checks: no real services or databases are started/stopped.
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$script:checks=0
function Assert-Check([bool]$Condition,[string]$Message){if(-not $Condition){throw $Message};$script:checks++}
function Assert-Rejected([scriptblock]$Action,[string]$Message){
 $rejected=$false
 try{& $Action | Out-Null}catch{$rejected=$true}
 Assert-Check $rejected $Message
}
function Get-NetTCPConnection {param($LocalPort,$State,$ErrorAction) return $script:listeners}
function Get-CimInstance {param($ClassName,$Filter,$ErrorAction) return $script:process}
function Start-Sleep {param($Milliseconds)}
function Invoke-WebRequest {
 param($Uri,[switch]$UseBasicParsing,$TimeoutSec)
 $script:httpAttempts++
 if($script:httpAttempts -lt $script:httpReadyAt){throw 'Mock service not ready.'}
 return [pscustomobject]@{StatusCode=200}
}
$fakeExecutable='C:\test\php.exe'
$fakeCommandPath='D:\test project\backend'
$script:listeners=@()
Assert-Check (-not (Test-LocalRuntimePort -Port 8000 -ExecutablePath $fakeExecutable -CommandPath $fakeCommandPath -Name 'Test API')) 'An unused port should be available.'
$script:listeners=@([pscustomobject]@{OwningProcess=123})
$script:process=[pscustomobject]@{ExecutablePath=$fakeExecutable;CommandLine='C:\test\php.exe -S 127.0.0.1:8000 "D:\test project\backend\vendor\server.php"'}
Assert-Check (Test-LocalRuntimePort -Port 8000 -ExecutablePath $fakeExecutable -CommandPath $fakeCommandPath -Name 'Test API') 'The project listener should be reused.'
$script:process.ExecutablePath='C:\other\php.exe'
Assert-Rejected {Test-LocalRuntimePort -Port 8000 -ExecutablePath $fakeExecutable -CommandPath $fakeCommandPath -Name 'Test API'} 'A foreign executable must be rejected.'
$script:process.ExecutablePath=$fakeExecutable
$script:process.CommandLine='C:\test\php.exe -S 127.0.0.1:8000 D:\other-project\server.php'
Assert-Rejected {Test-LocalRuntimePort -Port 8000 -ExecutablePath $fakeExecutable -CommandPath $fakeCommandPath -Name 'Test API'} 'Another project using the same PHP executable must be rejected.'
$script:process.CommandLine='mysqld --datadir=D:\other-project\mysql\data'
Assert-Rejected {Test-LocalRuntimePort -Port 3307 -ExecutablePath $fakeExecutable -CommandPath 'D:\test project\.local\mysql\data' -Name 'Test MySQL'} 'Another MySQL data directory must be rejected before any client command.'
$pidTestPath=Join-Path ([IO.Path]::GetTempPath()) ('clsu-runtime-pid-'+[guid]::NewGuid().ToString('N')+'.txt')
try{
 '' | Set-Content -LiteralPath $pidTestPath -NoNewline
 Assert-Check ((Get-LocalRuntimePid $pidTestPath) -eq 0) 'An empty PID file should be treated as stale.'
 'not a process ID' | Set-Content -LiteralPath $pidTestPath
 Assert-Check ((Get-LocalRuntimePid $pidTestPath) -eq 0) 'A damaged PID file should be treated as stale.'
 '123' | Set-Content -LiteralPath $pidTestPath
 Assert-Check ((Get-LocalRuntimePid $pidTestPath) -eq 123) 'A valid PID file should be readable.'
 $script:process.CommandLine='node node_modules/vite/bin/vite.js --port 5173'
 Assert-Check (Test-LocalRuntimePort -Port 5173 -ExecutablePath $fakeExecutable -CommandPath 'D:\test project\frontend\node_modules\vite\bin\vite.js' -Name 'Test frontend' -PidPath $pidTestPath -LegacyCommandPattern '[\\/]vite[\\/]bin[\\/]vite\.js') 'The previous launcher Vite process should remain compatible.'
 '999' | Set-Content -LiteralPath $pidTestPath
 Assert-Rejected {Test-LocalRuntimePort -Port 5173 -ExecutablePath $fakeExecutable -CommandPath 'D:\test project\frontend\node_modules\vite\bin\vite.js' -Name 'Test frontend' -PidPath $pidTestPath -LegacyCommandPattern '[\\/]vite[\\/]bin[\\/]vite\.js'} 'A different legacy process ID must not be reused.'
}finally{Remove-Item -LiteralPath $pidTestPath -Force}
$script:httpAttempts=0;$script:httpReadyAt=3
Wait-LocalRuntimeHttp -Uri 'http://test.invalid' -Name 'Mock API'
Assert-Check ($script:httpAttempts -eq 3) 'Readiness must retry until an HTTP success.'
$script:httpAttempts=0;$script:httpReadyAt=100
Assert-Rejected {Wait-LocalRuntimeHttp -Uri 'http://test.invalid' -Name 'Mock API'} 'Readiness failures must not report success.'
Assert-Check ($script:httpAttempts -eq 20) 'Readiness retries must be bounded.'
Write-Output "$script:checks local runtime checks passed. No running services or databases were changed."
