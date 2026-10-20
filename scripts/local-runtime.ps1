function Get-LocalRuntimePid([string]$Path) {
 $processId=0
 if(Test-Path -LiteralPath $Path){
  $savedValue=[string](Get-Content -LiteralPath $Path -Raw)
  if($savedValue -and [int]::TryParse($savedValue.Trim(),[ref]$processId) -and $processId -gt 0){return $processId}
 }
 return 0
}

function Test-LocalRuntimePort {
 param([int]$Port,[string]$ExecutablePath,[string]$CommandPath,[string]$Name,[string]$PidPath='',[string]$LegacyCommandPattern='')
 $listeners=@(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
 if(-not $listeners.Count){return $false}
 $knownProcessId=if($PidPath){Get-LocalRuntimePid $PidPath}else{0}
 foreach($listener in $listeners){
  $process=Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)" -ErrorAction SilentlyContinue
  $matchesPath=$process -and $process.CommandLine -and $process.CommandLine.IndexOf($CommandPath,[StringComparison]::OrdinalIgnoreCase) -ge 0
  # Older launcher versions used relative Vite arguments; accept only their saved process ID.
  $matchesLegacy=$process -and $knownProcessId -eq $listener.OwningProcess -and $LegacyCommandPattern -and $process.CommandLine -match $LegacyCommandPattern
  if(-not ($process -and $process.ExecutablePath -ieq $ExecutablePath -and ($matchesPath -or $matchesLegacy))){
   throw "$Name cannot start: port $Port is used by another process. No existing process was stopped."
  }
 }
 return $true
}

function Wait-LocalRuntimeHttp([string]$Uri,[string]$Name) {
 for($attempt=0;$attempt -lt 20;$attempt++){
  try {
   $response=Invoke-WebRequest -Uri $Uri -UseBasicParsing -TimeoutSec 1
   if($response.StatusCode -eq 200){return}
  } catch { }
  Start-Sleep -Milliseconds 250
 }
 throw "$Name did not become ready. Check its .local output and error logs."
}
