function Upload-Photo
{
    param (
        $baseUrl,
        $headers,
        $photoFile
    )

    # upload file to storage
    $uri = $baseUrl + '/storage/asyncUpload.php'
    $formUpload = @{
        'upload' = Get-Item -Path $photoFile
    }
    $resultUpload = Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -Form $formUpload
    $hash = $resultUpload.hash
    $fileName = $resultUpload.meta.name

    # import the file as portrait
    $uri = $baseUrl + '/adminv2/import/photos/upload'
    $formImport = @{
        'attachment[]' = $hash + ':' + $fileName
    }
    Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -Form $formImport
}

Set-Location $PSScriptRoot

$configData = Get-Content -Path .\config.txt -Raw;
$config = ConvertFrom-StringData -StringData $configData -Delimiter '='
$token = $config['token']
$baseUrl = $config['base-url']
$photoDir = $config['photo-dir']

# validate
$len = $token|Measure-Object -Character -IgnoreWhiteSpace
if ($len.Characters -eq 0) {
    Write-Error 'Config variable "token" is not set'
    Exit
}

try
{
    Invoke-WebRequest -Uri $baseUrl
}
catch {
    Write-Error 'Unable to access base-url: ' + $baseUrl
    Exit
}

$headers = @{
    'Authorization' = "Bearer $token"
    'accept' = 'application/json'
    'Content-Type' = 'application/json'
}

$photos = Get-ChildItem -Path $photoDir

foreach($photo in $photos)
{
    Upload-Photo -baseUrl $baseUrl -headers $headers -photoFile $photo.FullName
}
