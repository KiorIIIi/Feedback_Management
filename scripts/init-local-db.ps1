$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$mysqlBin='C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin'
$dataPath=Join-Path $projectRoot '.local\mysql\data'
$logPath=Join-Path $projectRoot '.local\mysql\mysql.log'
New-Item -ItemType Directory -Path (Split-Path $dataPath -Parent) -Force | Out-Null
if(-not (Test-Path (Join-Path $dataPath 'mysql'))){
 New-Item -ItemType Directory -Path $dataPath -Force | Out-Null
 & "$mysqlBin\mysqld.exe" --initialize-insecure "--basedir=$(Split-Path $mysqlBin -Parent)" "--datadir=$dataPath" --console
 if($LASTEXITCODE -ne 0){throw 'MySQL initialization failed.'}
}
if(-not (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)){
 Start-Process -FilePath "$mysqlBin\mysqld.exe" -ArgumentList @('--no-defaults',"--basedir=$(Split-Path $mysqlBin -Parent)","--datadir=$dataPath",'--port=3307','--bind-address=127.0.0.1',"--log-error=$logPath",'--mysqlx=OFF') -WindowStyle Hidden
}
for($attempt=0;$attempt -lt 30;$attempt++){
 & "$mysqlBin\mysqladmin.exe" --host=127.0.0.1 --port=3307 --user=root ping --silent 2>$null
 if($LASTEXITCODE -eq 0){break}
 Start-Sleep -Milliseconds 500
}
& "$mysqlBin\mysql.exe" --host=127.0.0.1 --port=3307 --user=root -e 'CREATE DATABASE IF NOT EXISTS clsu_feedback CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
if($LASTEXITCODE -ne 0){throw 'Could not create the isolated development database.'}
Write-Output 'Isolated local MySQL is ready at 127.0.0.1:3307. This database is for synthetic development data only.'
