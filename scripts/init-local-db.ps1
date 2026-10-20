$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$projectRoot=Split-Path -Parent $PSScriptRoot
$mysqlBin='C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin'
$dataPath=Join-Path $projectRoot '.local\mysql\data'
$logPath=Join-Path $projectRoot '.local\mysql\mysql.log'
foreach($file in @('mysqld.exe','mysql.exe','mysqladmin.exe')){
 if(-not (Test-Path -LiteralPath (Join-Path $mysqlBin $file) -PathType Leaf)){throw "MySQL executable $file is missing from $mysqlBin."}
}
# Never connect to or create a database on an unrelated server using this port.
$mysqlRunning=Test-LocalRuntimePort -Port 3307 -ExecutablePath "$mysqlBin\mysqld.exe" -CommandPath $dataPath -Name 'Isolated MySQL'
New-Item -ItemType Directory -Path (Split-Path $dataPath -Parent) -Force | Out-Null
if(-not (Test-Path (Join-Path $dataPath 'mysql'))){
 if($mysqlRunning){throw 'The running isolated MySQL data directory is incomplete. Stop and inspect it before initialization.'}
 New-Item -ItemType Directory -Path $dataPath -Force | Out-Null
 & "$mysqlBin\mysqld.exe" --no-defaults --initialize-insecure "--basedir=$(Split-Path $mysqlBin -Parent)" "--datadir=$dataPath" --console
 if($LASTEXITCODE -ne 0){throw 'MySQL initialization failed.'}
}
if(-not $mysqlRunning){
 Start-Process -FilePath "$mysqlBin\mysqld.exe" -ArgumentList @('--no-defaults',"`"--basedir=$(Split-Path $mysqlBin -Parent)`"","`"--datadir=$dataPath`"",'--port=3307','--bind-address=127.0.0.1',"`"--log-error=$logPath`"",'--mysqlx=OFF') -WindowStyle Hidden
}
$ready=$false
for($attempt=0;$attempt -lt 30;$attempt++){
 & "$mysqlBin\mysqladmin.exe" --no-defaults --connect-timeout=1 --host=127.0.0.1 --port=3307 --user=root ping --silent 2>$null
 if($LASTEXITCODE -eq 0){$ready=$true;break}
 Start-Sleep -Milliseconds 500
}
if(-not $ready){throw 'Isolated MySQL did not become ready. Check .local/mysql/mysql.log.'}
& "$mysqlBin\mysql.exe" --no-defaults --connect-timeout=1 --host=127.0.0.1 --port=3307 --user=root -e 'CREATE DATABASE IF NOT EXISTS clsu_feedback CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
if($LASTEXITCODE -ne 0){throw 'Could not create the isolated development database.'}
Write-Output 'Isolated local MySQL is ready at 127.0.0.1:3307. This database is for synthetic development data only.'
