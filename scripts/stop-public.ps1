param([string]$PhpPath='C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64\php.exe')
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$projectRoot=Split-Path -Parent $PSScriptRoot
$runtimeDir=Join-Path $projectRoot '.local/public-host'
$targets=@(
 @{Name='tunnel';Executable=(Join-Path $projectRoot '.local/tools/cloudflared.exe');Command='tunnel'},
 @{Name='worker';Executable=$PhpPath;Command=(Join-Path $PSScriptRoot 'public-worker.php')},
 @{Name='web';Executable=$PhpPath;Command=(Join-Path $PSScriptRoot 'public-router.php')}
)
foreach($target in $targets){
 $pidFile=Join-Path $runtimeDir ($target.Name+'.pid')
 $processId=Get-LocalRuntimePid $pidFile
 if(-not $processId){continue}
 $process=Get-CimInstance Win32_Process -Filter "ProcessId=$processId" -ErrorAction SilentlyContinue
 if($process){
  if($process.ExecutablePath -ine $target.Executable -or -not $process.CommandLine -or $process.CommandLine.IndexOf($target.Command,[StringComparison]::OrdinalIgnoreCase) -lt 0){throw "The saved $($target.Name) process no longer matches this project. It was not stopped."}
  Stop-Process -Id $processId -ErrorAction Stop
 }
 if(Test-Path -LiteralPath $pidFile){Remove-Item -LiteralPath $pidFile}
}
Write-Output 'Public link, web process, and public queue worker stopped. Databases and local development services were preserved.'
