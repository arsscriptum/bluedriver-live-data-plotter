@echo off
setlocal

:: Get the directory of the batch file
set "SCRIPT_DIR=%~dp0"

:: Launch PowerShell 7 with the TryStart.ps1 script from the same folder
"C:\Programs\PowerShell\7\pwsh.exe" -NoLogo -NoProfile -NonInteractive -File "%SCRIPT_DIR%\ps\TryStop.ps1"

endlocal
