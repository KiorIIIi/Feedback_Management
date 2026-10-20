param([string]$PhpPath='C:\laragon\bin\php\php-8.5.10-Win32-vs17-x64\php.exe')
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$projectRoot=Split-Path -Parent $PSScriptRoot
$runtimeDir=Join-Path $projectRoot '.local'
New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
if(-not (Test-Path "$projectRoot\backend\.env")){throw 'Run the setup steps in README.md first.'}
if(-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)){throw 'PHP was not found. Pass the installed PHP 8.5 executable with -PhpPath.'}
if(-not (Test-Path "$projectRoot\backend\vendor\autoload.php")){throw 'Backend dependencies are missing. Run composer install --working-dir=backend.'}
if(-not (Test-Path "$projectRoot\frontend\node_modules\vite\bin\vite.js")){throw 'Frontend dependencies are missing. Run npm --prefix frontend ci.'}
$PhpPath=(Resolve-Path -LiteralPath $PhpPath).Path
$nodePath=(Get-Command node.exe -ErrorAction Stop).Source
$env:PATH=(Split-Path $PhpPath -Parent)+';'+$env:PATH
$backendRunning=Test-LocalRuntimePort -Port 8000 -ExecutablePath $PhpPath -CommandPath "$projectRoot\backend" -Name 'Laravel API'
$frontendRunning=Test-LocalRuntimePort -Port 5173 -ExecutablePath $nodePath -CommandPath "$projectRoot\frontend\node_modules\vite\bin\vite.js" -Name 'Frontend' -PidPath "$runtimeDir\frontend.pid" -LegacyCommandPattern '[\\/]vite[\\/]bin[\\/]vite\.js'
if(-not $backendRunning){
 $backend=Start-Process -FilePath $PhpPath -ArgumentList @("`"$projectRoot\backend\artisan`"",'serve','--host=127.0.0.1','--port=8000','--no-reload') -WorkingDirectory "$projectRoot\backend" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$runtimeDir\backend.out.log" -RedirectStandardError "$runtimeDir\backend.err.log"
 $backend.Id | Set-Content "$runtimeDir\backend.pid"
}
if(-not $frontendRunning){
 $frontend=Start-Process -FilePath $nodePath -ArgumentList @("`"$projectRoot\frontend\node_modules\vite\bin\vite.js`"",'--host','127.0.0.1','--port','5173','--strictPort') -WorkingDirectory "$projectRoot\frontend" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$runtimeDir\frontend.out.log" -RedirectStandardError "$runtimeDir\frontend.err.log"
 $frontend.Id | Set-Content "$runtimeDir\frontend.pid"
}
Wait-LocalRuntimeHttp -Uri 'http://127.0.0.1:8000/up' -Name 'Laravel API'
Wait-LocalRuntimeHttp -Uri 'http://127.0.0.1:5173/' -Name 'Frontend'
$workerRunning=$false
$workerProcessId=Get-LocalRuntimePid "$runtimeDir\queue.pid"
if($workerProcessId){
 $existingWorker=Get-CimInstance Win32_Process -Filter "ProcessId=$workerProcessId" -ErrorAction SilentlyContinue
 $workerRunning=$existingWorker -and $existingWorker.CommandLine -like '*artisan*queue:work*' -and $existingWorker.ExecutablePath -eq $PhpPath
}
if(-not $workerRunning){
 $worker=Start-Process -FilePath $PhpPath -ArgumentList @("`"$projectRoot\backend\artisan`"",'queue:work','--sleep=1','--tries=3','--timeout=90') -WorkingDirectory "$projectRoot\backend" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$runtimeDir\queue.out.log" -RedirectStandardError "$runtimeDir\queue.err.log"
 $worker.Id | Set-Content "$runtimeDir\queue.pid"
}
Write-Output 'Portal: http://127.0.0.1:5173  |  API: http://127.0.0.1:8000'
