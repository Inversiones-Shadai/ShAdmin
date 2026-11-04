$ErrorActionPreference='Stop'
$taskRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskServers=@(Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq 'php.exe' -and $_.CommandLine -like '*-S 127.0.0.1:8001*' -and
    $_.CommandLine.Replace('\','/').Contains($taskRoot.Replace('\','/'))
})
if($taskServers.Count -ne 1) { throw 'Se esperaba un único servidor ShAdmin en 127.0.0.1:8001.' }
$taskServer=$taskServers[0]
$taskParent=Get-CimInstance Win32_Process -Filter ('ProcessId='+$taskServer.ParentProcessId)
if($taskParent.Name -eq 'cmd.exe' -and $taskParent.CommandLine -like '*127.0.0.1:8001*' -and
    $taskParent.CommandLine.Replace('\','/').Contains($taskRoot.Replace('\','/'))) {
    $taskParent=Get-CimInstance Win32_Process -Filter ('ProcessId='+$taskParent.ParentProcessId)
}
if(-not $taskParent -or $taskParent.ExecutablePath -ne $taskServer.ExecutablePath -or
    $taskParent.CommandLine -notmatch 'artisan\s+serve\b' -or
    $taskParent.CommandLine -notmatch '--port(?:=|\s+)8001\b') {
    throw 'El proceso padre no coincide con el servidor Artisan esperado.'
}
$taskOutput=Join-Path $taskRoot 'artifacts/catalog-refresh/server-output.log'
$taskError=Join-Path $taskRoot 'artifacts/catalog-refresh/server-error.log'
Stop-Process -Id $taskParent.ProcessId
if(Get-Process -Id $taskServer.ProcessId -ErrorAction SilentlyContinue) { Stop-Process -Id $taskServer.ProcessId }
$taskNew=Start-Process -FilePath $taskServer.ExecutablePath -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=8001','--tries=1') -WorkingDirectory $taskRoot -WindowStyle Hidden -RedirectStandardOutput $taskOutput -RedirectStandardError $taskError -PassThru
Write-Output ('Servidor ShAdmin reiniciado con el comando actualizado. PID '+$taskNew.Id)
