param([ValidatePattern('^\d{4}\.\d+\.\d+$')][string]$Version='2026.10.2')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$toolsDir=Join-Path $projectRoot '.local/tools'
New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null
$release=Invoke-RestMethod -Uri "https://api.github.com/repos/cloudflare/cloudflared/releases/tags/$Version"
$asset=$release.assets | Where-Object name -eq 'cloudflared-windows-amd64.exe'
if(-not $asset -or $asset.digest -notmatch '^sha256:[a-f0-9]{64}$'){throw 'Official release asset with a SHA-256 digest was not found.'}
$downloadPath=Join-Path $toolsDir 'cloudflared.download.exe'
Invoke-WebRequest -Uri $asset.browser_download_url -OutFile $downloadPath
$hash=(Get-FileHash -LiteralPath $downloadPath -Algorithm SHA256).Hash.ToLowerInvariant()
if($asset.digest -ne ('sha256:'+$hash)){throw 'Official release checksum did not match; the downloaded binary was not installed.'}
Move-Item -LiteralPath $downloadPath -Destination (Join-Path $toolsDir 'cloudflared.exe') -Force
@{Version=$Version;Sha256=$hash;Source=$asset.browser_download_url} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $toolsDir 'cloudflared-release.json')
Write-Output "Installed project-local cloudflared $Version; official SHA-256 verified."
