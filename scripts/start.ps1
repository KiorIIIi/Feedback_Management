param([string]$PhpPath='C:\laragon\bin\php\php-8.5.10-Win32-vs17-x64\php.exe')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$runtimeDir=Join-Path $projectRoot '.local'
New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
$env:PATH=(Split-Path $PhpPath -Parent)+';'+$env:PATH
if(-not (Test-Path "$projectRoot\backend\.env")){throw 'Run the setup steps in README.md first.'}
if(-not (Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue)){
 $backend=Start-Process -FilePath $PhpPath -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=8000','--no-reload') -WorkingDirectory "$projectRoot\backend" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$runtimeDir\backend.out.log" -RedirectStandardError "$runtimeDir\backend.err.log"
 $backend.Id | Set-Content "$runtimeDir\backend.pid"
}
if(-not (Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue)){
 $frontend=Start-Process -FilePath (Get-Command node.exe).Source -ArgumentList @('node_modules/vite/bin/vite.js','--host','127.0.0.1','--port','5173','--strictPort') -WorkingDirectory "$projectRoot\frontend" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$runtimeDir\frontend.out.log" -RedirectStandardError "$runtimeDir\frontend.err.log"
 $frontend.Id | Set-Content "$runtimeDir\frontend.pid"
}
$workerRunning=$false
if(Test-Path "$runtimeDir\queue.pid"){
 $workerProcessId=[int](Get-Content "$runtimeDir\queue.pid")
 $existingWorker=Get-CimInstance Win32_Process -Filter "ProcessId=$workerProcessId" -ErrorAction SilentlyContinue
 $workerRunning=$existingWorker -and $existingWorker.CommandLine -like '*artisan*queue:work*' -and $existingWorker.ExecutablePath -eq $PhpPath
}
if(-not $workerRunning){
 $worker=Start-Process -FilePath $PhpPath -ArgumentList @('artisan','queue:work','--sleep=1','--tries=3','--timeout=90') -WorkingDirectory "$projectRoot\backend" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$runtimeDir\queue.out.log" -RedirectStandardError "$runtimeDir\queue.err.log"
 $worker.Id | Set-Content "$runtimeDir\queue.pid"
}
Write-Output 'Portal: http://127.0.0.1:5173  |  API: http://127.0.0.1:8000'
