function Import-CSV
{
    param (
        $baseUrl,
        $headers,
        $csvType,
        $csvFile
    )

    # upload file
    $expiryDate = (Get-Date).AddDays(7) | Get-Date -Format 'yyyy-MM-dd\THH:mm:ssP'
    $expiryDate = [System.Web.HttpUtility]::UrlEncode($expiryDate)

    $uri = $baseUrl + '/storage/asyncUpload.php?expiryDate=' + $expiryDate
    $formUpload = @{
        'upload' = Get-Item -Path $csvFile
    }
    $resultUpload = Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -Form $formUpload
    $hash = $resultUpload.hash
    $fileName = $resultUpload.meta.name

    # import file
    $uri = $baseUrl + '/adminv2/import/profile/upload?perform=1'
    $formImport = @{
        'file' = $csvType
        'attachment[]' = $hash + ':' + $fileName
    }
    Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -Form $formImport
}

Set-Location $PSScriptRoot

$configData = Get-Content -Path .\config.txt -Raw;
$config = ConvertFrom-StringData -StringData $configData -Delimiter '='
$token = $config['token']
$baseUrl = $config['base-url']
$csvDir = $config['csv-dir']

# validate
$len = $token|Measure-Object -Character -IgnoreWhiteSpace
if ($len.Characters -eq 0)
{
    Write-Error 'Config variable "token" is not set'
    Exit
}

try
{
    Invoke-WebRequest -Uri $baseUrl
}
catch
{
    Write-Error 'Unable to access base-url: ' + $baseUrl
    Exit
}

$headers = @{
    'Authorization' = "Bearer $token"
    'accept' = 'application/json'
    'Content-Type' = 'application/json'
}

$csvTypes = 'staff', 'student', 'parent', 'relationship'

foreach ($csvType in $csvTypes)
{
    $csvFile = $config["csv-$csvType"]
    $csvFile = "$PSScriptRoot\$csvDir\" + $csvFile
    Import-Csv -baseUrl $baseUrl -headers $headers -csvType $csvType -csvFile $csvFile
}
