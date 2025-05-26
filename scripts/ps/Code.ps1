
function Convert-MyScriptScriptBlock {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Position = 0, Mandatory = $true, ValueFromPipeLine = $True, HelpMessage = "The Code")]
        [string]$ScriptBlock
    )
    process {
        # Take my B64 string and do a Base64 to Byte array conversion of compressed data
        $ScriptBlockCompressed = [System.Convert]::FromBase64String($ScriptBlock)

        # Then decompress script's data
        $InputStream = New-Object System.IO.MemoryStream (, $ScriptBlockCompressed)
        $GzipStream = New-Object System.IO.Compression.GzipStream $InputStream, ([System.IO.Compression.CompressionMode]::Decompress)
        $StreamReader = New-Object System.IO.StreamReader ($GzipStream)
        $ScriptBlockDecompressed = $StreamReader.ReadToEnd()
        # And close the streams
        $GzipStream.Close()
        $InputStream.Close()

        $ScriptBlockDecompressed
    }
}

$DecodedScript = Convert-MyScriptScriptBlock $EncodedScript

[scriptblock]$sb = [scriptblock]::Create($DecodedScript)
Invoke-Command -ScriptBlock $sb

