[CmdletBinding(SupportsShouldProcess)]
param(
    [Parameter(Mandatory = $false, Position = 0)]
    [switch]$Json
)


function Register-HtmlAgilityPack {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory = $False)]
        [string]$Path
    )
    begin {
        if ([string]::IsNullOrEmpty($Path)) {
            $Path = "{0}\lib\{1}\HtmlAgilityPack.dll" -f "$PSScriptRoot", "$($PSVersionTable.PSEdition)"
        }
    }
    process {
        try {
            if (-not (Test-Path -Path "$Path" -PathType Leaf)) { throw "no such file `"$Path`"" }
            if (!("HtmlAgilityPack.HtmlDocument" -as [type])) {
                Write-Verbose "Registering HtmlAgilityPack... "
                add-type -Path "$Path"
            } else {
                Write-Verbose "HtmlAgilityPack already registered "
            }
        } catch {
            throw $_
        }
    }
}

function Get-StatsLinks {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory = $false, Position = 0)]
        [switch]$Json
    )

    try {

        Add-Type -AssemblyName System.Web

        $Null = Register-HtmlAgilityPack

        $Ret = $False

        $Url = "https://support.bluedriver.com/support/solutions/articles/43000551789-live-data-guide"
        $HeadersData = @{
            "authority" = "support.bluedriver.com"
            "method" = "GET"
            "path" = "/support/solutions/articles/43000551789-live-data-guide"
            "scheme" = "https"
            "accept" = "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8"
            "accept-encoding" = "gzip, deflate, br, zstd"
            "accept-language" = "en-US,en;q=0.8"
            "cache-control" = "no-cache"
            "pragma" = "no-cache"
            "priority" = "u=0, i"
        }
        $Results = Invoke-WebRequest -UseBasicParsing -Uri $Url -Headers $HeadersData
        $Data = $Results.Content
        if ($Results.StatusCode -eq 200) {
            $Ret = $True
        }

        $HtmlContent = $Results.Content

        [HtmlAgilityPack.HtmlDocument]$HtmlDoc = @{}
        $HtmlDoc.LoadHtml($HtmlContent)
        $HtmlNode = $HtmlDoc.DocumentNode
        Set-Content "$PSscriptRoot\test.html" -Value "$HtmlContent"
        [System.Collections.ArrayList]$ParsedList = [System.Collections.ArrayList]::new()

        for ($tableId = 1; $tableId -lt 5; $tableId++) {
            for ($statsId = 1; $statsId -lt 100; $statsId++) {
                try {


                    $XPath = "/html/body/div/div[2]/section[1]/article/div/table[$tableId]/tbody/tr[$statsId]/td[1]"

                    $ResultNode = $HtmlNode.SelectSingleNode($XPath)

                    if (!$ResultNode) {
                        Write-Verbose "$tableId,$statsId EMPTY"
                        continue;
                    }

                    [string]$Name = $ResultNode.InnerHtml
                    $NewName = $Name.Replace(' ', '_')
                    $NewName = $NewName -replace '[^a-zA-Z0-9_]', ''
                    $NewName = $NewName.Replace('__', '_').ToLower()

                    $TmpHref = ''
                    if ([string]::IsNullOrEmpty($ResultNode.Id)) {
                        $TmpHref = 'pid-' + $NewName.Replace('_', '-')
                    } else {
                        $TmpHref = $ResultNode.Id
                        $TmpHref = $TmpHref.Replace('__', '_').Replace('--', '-').Replace('--', '-').Replace('--', '-')
                    }
                    [string]$HrefId = $TmpHref

                    [pscustomobject]$o = [pscustomobject]@{
                        DisplayName = "$Name"
                        Name = "$NewName"
                        HrefId = "$HrefId"
                    }
                    Write-Verbose "ok"
                    [void]$ParsedList.Add($o)
                } catch {
                    Write-Verbose "$_"
                    continue;
                }
            }
        }
        if ($Json) {
            $ParsedList | ConvertTo-Json
        } else {
            return $ParsedList
        }


    }
    catch {
        Write-Warning "Error occurred: $_"
        return $null
    }
}


function Convert-DisplayName {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(ValueFromPipeLine = $True, Mandatory = $True, Position = 0)]
        [string]$DisplayName
    )

    process {
        $NewName = $DisplayName.Replace(' ', '_')
        $NewName = $NewName -replace '[^a-zA-Z0-9_]', ''
        $NewName = $NewName.Replace('__', '_').ToLower().Trim('_').Trim()
        $NewName
    }

}

Get-StatsLinks -Json:$Json

