

function Write-BdLog {
    [CmdletBinding()]
    param(
        [Parameter(Position = 0, Mandatory = $true, HelpMessage = "The message to log")]
        [string]$Message
    )

    # Format and display on console
    Write-Host -NoNewline "[BdViewer] " -ForegroundColor DarkRed
    Write-Host $Message -ForegroundColor DarkYellow

    # Log file path
    $logFile = Join-Path $env:TEMP 'bdviewer.log'

    # Append message with timestamp
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    "$timestamp [BdViewer] $Message" | Out-File -FilePath $logFile -Encoding UTF8 -Append
}


function Write-BdLogError {

    [CmdletBinding()]
    param
    (
        [Parameter(Mandatory = $true)]
        [System.Management.Automation.ErrorRecord]$Record,
        [Parameter(Mandatory = $false)]
        [switch]$ShowStack
    )
    $formatstring = "{0}`n{1}"
    $fields = $Record.FullyQualifiedErrorId, $Record.Exception.ToString()
    $ExceptMsg = ($formatstring -f $fields)
    $Stack = $Record.ScriptStackTrace
    Write-Host "`n[ERROR] -> " -NoNewline -ForegroundColor DarkRed;
    Write-Host "$ExceptMsg`n`n" -ForegroundColor DarkYellow
    if ($ShowStack) {
        Write-Host "--stack begin--" -ForegroundColor DarkGreen
        Write-Host "$Stack" -ForegroundColor Gray
        Write-Host "--stack end--`n" -ForegroundColor DarkGreen
    }
    if ((Get-Variable -Name 'ShowExceptionDetailsTextBox' -Scope Global -ErrorAction Ignore -ValueOnly) -eq 1) {
        Show-MessageBoxException $ExceptMsg $Stack
    }


}



function Get-BdViewerProcess {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    $ps = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like "*--user-data-dir=$env:TEMP\BdViewerProfile*" }
    if(!$ps){
        return $Null
    }
    $ProcessId = $ps.ProcessId
    $PwshProcess = Get-Process -Id $ProcessId -ErrorAction Ignore
    return $PwshProcess
}


function Open-BraveWindow
{
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory = $true, Position = 0)]
        [string]$Url,
        [Parameter(Mandatory = $false)]
        [switch]$Incognito,
        [Parameter(Mandatory = $false)]
        [switch]$DisableExtensions
    )

    $ErrorActionPreference = 'Stop'

    try {
        $RetObj = [pscustomobject]@{}

        $userProfileDir = "$env:TEMP\BdViewerProfile"
        $ExePath = Get-BravePath
        $ArgumentList = @()
        $ArgumentList += "--remote-debugging-port=9222"
        $ArgumentList += "--new-window"
        $ArgumentList += "--user-data-dir=`"$userProfileDir`""
        $ArgumentList += "$Url"
        if ($DisableExtensions) {
            $ArgumentList += "--disable-extensions" # Faster dev startup
        }
        if ($Incognito) {
            $ArgumentList += "--incognito" # Launch in incognito mode
        }
        # Ensure profile directory exists
        New-Item -ItemType Directory -Path "$userProfileDir" -Force | Out-Null

        $cmd = Start-Process -FilePath "$ExePath" -ArgumentList $ArgumentList -Passthru | ForEach-Object {
            $_ | Set-Content "$env:TEMP\BdViewer.brave.pid"
        }

        Start-Sleep 2

        $BdViewerProcess = Get-BdViewerProcess
        if (!$BdViewerProcess) {
            $RetObj | Add-Member -MemberType NoteProperty -Name "Success" -Value $False -Force
            $RetObj | Add-Member -MemberType NoteProperty -Name "ProcessId" -Value 0 -Force
            return $RetObj
        }
        $RunningPid = $BdViewerProcess.Id

        $RetObj | Add-Member -MemberType NoteProperty -Name "ProcessId" -Value $RunningPid -Force
        $RetObj | Add-Member -MemberType NoteProperty -Name "Success" -Value $True -Force

        Write-BdLog "[Open-BraveWindow] Started PID $RunningPid"

        return $RetObj

    }
    catch {
        Show-ExceptionDetails $_ -ShowStack
    }
}

function Watch-BdViewerMemory {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory = $false)]
        [int]$IntervalSeconds = 0
    )

    Write-Host "Monitoring Brave memory usage... (Ctrl+C to stop)" -ForegroundColor Cyan
    $Loop = $False
    if ($IntervalSeconds -gt 0) {
        $Loop = $True
    }
    if ($Loop) {

        while ($Loop) {
            $process = Get-CimInstance Win32_Process | Where-Object {
                $_.CommandLine -like "*--user-data-dir=$env:TEMP\BdViewerProfile*"
            }

            if ($process) {
                $wsMB = [math]::Round($process.WorkingSetSize / 1MB, 2)
                $privateMB = [math]::Round($process.PrivatePageCount / 1MB, 2)
                $time = Get-Date -Format "HH:mm:ss"
                if ($wsMB -gt 500) {
                    Write-Warning "High memory usage detected! ($wsMB MB)"
                }

                Write-Host "[$time] PID $($process.ProcessId) | WorkingSet: ${wsMB}MB | Private: ${privateMB}MB"
            } else {
                Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Process not found." -ForegroundColor DarkGray
            }

            Start-Sleep -Seconds $IntervalSeconds
        }
    }
}


function Start-BdViewer {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    try {
        $RegistryPath = "$ENV:OrganizationHKCU\BlueDriverStatsViewer"

        [decimal]$CurrentTimeSec = Get-Date -UFormat %s
        $IsBdViewRunning = Test-BdViewRunning
        if ($IsBdViewRunning) {
            # .. Stop
        }
        $Url = "http://mini:82/plotter.html"
        $res = Open-BraveWindow -Url "$Url"
        if ($res.Success) {
            Write-BdLog "Started Brave Browser, PID $($res.Id)"
        } else {
            Write-BdLog "Failed to start Brave Browser"
        }
        return $res.Success
    }
    catch {
        Write-BdLogError "Error occurred: $_"
        return $false
    }
}


function Stop-BdViewer {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory = $false, Position = 0)]
        [switch]$Json
    )

    try {
        $BdViewerProcess = Get-BdViewerProcess
        if (!$BdViewerProcess) {
            return $False
        }
        $RunningPid = $BdViewerProcess.Id
        Write-BdLog "[Stop-BdViewer] Will be Stopping PID $RunningPid"

        $BdViewerProcess | Stop-Process -Force

        Close-BdViewerViaWebSocket

    }
    catch {
        Write-BdLogError "Error occurred: $_"
        return $null
    }
}




function Test-BdViewRunning {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    $IsRunning = ((Get-BdViewerProcess) -ne $Null)
    Write-BdLog "[Test-BdViewRunning] IsBrowserRunning $IsRunning"
    return $IsRunning
}






function Get-BdViewRunTime {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    [decimal]$CurrentTimeSec = Get-Date -UFormat %s

    $IsRunning = Test-BdViewRunning
    Write-BdLog "[Test-BdViewRunning] IsBrowserRunning $IsRunning"
    if (!$IsRunning) {
        return 0
    }

    $CimProcess = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like "*--user-data-dir=$env:TEMP\BdViewerProfile*" }
    if(!$CimProcess){
        return 0
    }

    $RetTimeSpan = New-TimeSpan $CimProcess.CreationDate (Get-Date)
    return $RetTimeSpan
}



function Import-WebSocketSharp {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory = $false, Position = 0)]
        [switch]$Json
    )

    try {
        $CoreModuleExportsPath = Get-CoreModuleExportsPath
        $PathInfo = Resolve-Path -Path "$CoreModuleExportsPath\websocket-sharp.dll" -ErrorAction Stop
        $RawLibPath = $PathInfo.Path
        $NewLibType = Add-Type -Path $RawLibPath -Verbose -PassThru
        if(!$NewLibType){
            return $False
        }
        $True
    }
    catch {
        Write-BdLogError "Error occurred: $_"
        return $null
    }
}


function Close-BdViewerViaWebSocket {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    try {
        if(!(Import-WebSocketSharp)){
            throw "no websocket!"
        }

        $BdViewerWebSocketUrl = Get-BdViewerWebSocketUrl
        Write-Verbose "BdViewerWebSocketUrl $BdViewerWebSocketUrl"

        $ws = [WebSocketSharp.WebSocket]::new($BdViewerWebSocketUrl)

        # Open connection
        $ws.Connect()

        # Send Browser.close command (DevTools Protocol)
        $closeCmd = @{
            id = 1
            method = "Browser.close"
        } | ConvertTo-Json -Compress

        $Ret = $ws.Send($closeCmd)

        # Close connection
        $Ret = $ws.Close()
    }catch {
        Write-BdLogError "Error occurred: $_"
        return $null
    }finally{
        $ws.Dispose()
    }
}


function Get-BdViewerWebSocketUrl {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory = $false, Position = 0)]
        [int]$Port=9222
    )

    try {
        $TmpUrl = "http://localhost:{0}/json/version" -f $Port
        $debugInfo = Invoke-RestMethod -Uri $TmpUrl
        $webSocketUrl = $debugInfo.webSocketDebuggerUrl
        $webSocketUrl
        

    }catch {
        Write-BdLogError "Error occurred: $_"
        return $null
    }
}


Stop-BdViewer