param([Parameter(Mandatory=$true)][string]$Directory)
$ErrorActionPreference='Stop'
# PHP may inherit PowerShell 7's module search path while launching Windows PowerShell.
Import-Module (Join-Path $PSHOME 'Modules/Microsoft.PowerShell.Security/Microsoft.PowerShell.Security.psd1')

# Limit ACL changes to project-local runtime directories; never follow junctions.
$runtimeRoot=[IO.Path]::GetFullPath((Join-Path (Split-Path -Parent $PSScriptRoot) '.local'))
$target=[IO.Path]::GetFullPath($Directory).TrimEnd([IO.Path]::DirectorySeparatorChar)
if(-not $target.StartsWith($runtimeRoot+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){
 throw 'Secret directory must be inside this project''s .local directory.'
}
$cursor=$target
while($cursor -and $cursor.StartsWith($runtimeRoot,[StringComparison]::OrdinalIgnoreCase)){
 $item=Get-Item -LiteralPath $cursor -Force
 if($item.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Runtime paths must not contain reparse points.'}
 if($cursor -eq $runtimeRoot){break}
 $cursor=Split-Path -Parent $cursor
}
$identity=[Security.Principal.WindowsIdentity]::GetCurrent().User
$acl=New-Object Security.AccessControl.DirectorySecurity
$acl.SetAccessRuleProtection($true,$false)
$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new(
 $identity,[Security.AccessControl.FileSystemRights]::FullControl,
 [Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit',
 [Security.AccessControl.PropagationFlags]::None,[Security.AccessControl.AccessControlType]::Allow))
Set-Acl -LiteralPath $target -AclObject $acl
foreach($name in @('runtime.json','access.json')){
 $path=Join-Path $target $name
 if(Test-Path -LiteralPath $path){
  $item=Get-Item -LiteralPath $path -Force
  if($item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)){throw 'Secret files must be ordinary files.'}
  $fileAcl=New-Object Security.AccessControl.FileSecurity
  $fileAcl.SetAccessRuleProtection($true,$false)
  $fileAcl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($identity,'FullControl','Allow'))
  Set-Acl -LiteralPath $path -AclObject $fileAcl
 }
}
