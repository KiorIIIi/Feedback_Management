# Uses synthetic files in one temporary project-local directory; no live secrets are read.
$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
$directory=Join-Path $root ('.local/secret-acl-test-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $directory -Force | Out-Null
$checks=0
try {
 foreach($name in @('runtime.json','access.json')){Set-Content -LiteralPath (Join-Path $directory $name) -Value '{"synthetic":true}'}
 & (Join-Path $PSScriptRoot 'protect-public-secrets.ps1') -Directory $directory
 Set-Content -LiteralPath (Join-Path $directory 'new-secret.json') -Value '{"synthetic":true}'
 $current=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value
 foreach($path in @($directory,(Join-Path $directory 'runtime.json'),(Join-Path $directory 'access.json'),(Join-Path $directory 'new-secret.json'))){
  $acl=Get-Acl -LiteralPath $path
  $rules=@($acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier]))
  if($rules.Count -ne 1 -or $rules[0].IdentityReference.Value -ne $current -or $rules[0].AccessControlType -ne 'Allow' -or $rules[0].FileSystemRights -ne 'FullControl'){
   throw 'Secret ACL must grant access only to the current Windows identity.'
  }
  $checks++
 }
 foreach($path in @($root,(Join-Path $root '.local'))){
  $rejected=$false
  try{& (Join-Path $PSScriptRoot 'protect-public-secrets.ps1') -Directory $path}catch{$rejected=$true}
  if(-not $rejected){throw 'ACL protection accepted a path outside its permitted scope.'}
  $checks++
 }
 Write-Output "$checks private-file ACL checks passed. No live runtime files changed."
} finally {
 foreach($name in @('runtime.json','access.json','new-secret.json')){
  $path=Join-Path $directory $name
  if(Test-Path -LiteralPath $path){Remove-Item -LiteralPath $path -Force}
 }
 Remove-Item -LiteralPath $directory -Force
}
