[CmdletBinding(SupportsShouldProcess)]
param(
    [Parameter(Position = 0, Mandatory = $true, ValueFromPipeLine = $True, HelpMessage = "The Code")]
    [string]$ScriptPath
)

$EncodedScript = Convert-ToBase64CompressedFile -Path "$ScriptPath"
$BaseName = Get-Item -Path "$ScriptPath" | Select -ExpandProperty Basename
$String = "`$EncodedScript = `"{0}`"" -f $EncodedScript
$OutFileName = "Try{0}.ps1" -f $BaseName
$OutFilePath = Join-Path "$PWD" "$OutFileName"
New-Item -Path "$OutFilePath" -ItemType File -Force -ErrorAction Ignore | Out-Null


$String | Add-Content "$OutFilePath" 
Get-Content -Path "$PSScriptRoot\Code.ps1" -Raw | Add-Content "$OutFilePath" 