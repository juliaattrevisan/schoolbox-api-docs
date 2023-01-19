# importCsv.ps1
# Uploads CSV profile files to Schoolbox instance and import them to Schoolbox connector tables.

# Configuration

# Set base configuration from Parse config.txt file in the same folder with the following content
#
# csv-dir=<Path of the folder which has csv files. This folder must exist.>
# photo-dir=<Path of the folder which has photo files. The folder must exist in the same folder.>
# photo-upload-all=<If `true`, upload all the files in photo-dir.>
# token=<Token of a Schoolbox superuser.>
# base-url=<Base URL of a Schoolbox instance, e.g. https://demo.schoolbox.com.au.>
# csv-student=<The filename of student csv file under the dirrectory set as csv-dir.>
# csv-staff=<The filename of staff csv file under the dirrectory set as csv-dir.>
# csv-relationship=<The filename of relationship csv file under the dirrectory given as csv-dir.>
# csv-parent=<The filename of parent csv file under the dirrectory given as csv-dir.>

# As Get-Content and ConvertFrom-String cmdlets does not work consistently between running from shell and from task scheduler
# using System.IO.File here
# $configData = Get-Content -Path "$PSScriptRoot\config.txt" -Raw
# $myConfig = ConvertFrom-StringData -StringData $configData -Delimiter '='
$myConfig = @{ }
foreach ($line in [System.IO.File]::ReadLines("$PSScriptRoot\config.ini"))
{
    $parts = $line.Split('=')
    if ($parts.Count -eq 2)
    {
        $myConfig[$parts[0]] = $parts[1]
    }
}
$token = $myConfig['token']
$baseUrl = $myConfig['base-url']
$csvDir = $myConfig['csv-dir']
# Type of csv files
$csvTypes = 'staff', 'student', 'parent', 'relationship'
# Expiry date of the storage files - 7 days from today
$Global:expiryDate = (Get-Date).AddDays(7) | Get-Date -AsUTC -Format 'yyyy-MM-dd\THH:mm:ssK'
# Log file
$Global:LogPath = "$PSScriptRoot\logs\import_csv.log"
if (-not(Test-Path $Global:LogPath))
{
    New-Item -Path $Global:LogPath -Force
}

# Functions

# Function Write-Log
#  Log messages to logs file
# @param string $message Message to log
# @param bool $isError Whether the message is for an error
function Write-Log
{
    param (
        $message,
        $isError
    )
    $timeStamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Add-Content -Path $Global:LogPath "[$timeStamp] $message"

    if ($isError)
    {
        Write-host -f Red $message
    }
}

# Function Send-CSV
#  Upload a photo file to storage and import it as a portrait photo
#  @param string baseUrl Base URL of API endpoint
#  @param array headers Headers for API
#  @param string csvType Either 'staff', 'student', 'parent' or 'relationship'
#  @param string csvFile Full path of the csv file to import
function Send-CSV
{
    param (
        $baseUrl,
        $headers,
        $csvType,
        $csvFile
    )

    # upload file
    $expiryDate = [System.Web.HttpUtility]::UrlEncode($Global:expiryDate)
    $uri = $baseUrl + '/storage/asyncUpload.php?expiryDate=' + $expiryDate
    $formUpload = @{
        'upload' = Get-Item -Path $csvFile
    }
    $resultUpload = Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -Form $formUpload
    $hash = $resultUpload.hash
    $fileName = $resultUpload.meta.name
    Write-Log -message ("$fileName storage hash is $hash") -isError $false

    # import file
    $uri = $baseUrl + '/adminv2/import/profile/upload?perform=1'
    $formImport = @{
        'file' = $csvType
        'attachment[]' = $hash + ':' + $fileName
    }
    Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -Form $formImport > $null
}

# MAIN
Set-Location $PSScriptRoot
Write-Log -message "== Started importing CSV files ==" -isError $false

# Validate Base URL and token
try
{
    Invoke-WebRequest -Uri "$baseUrl/api/session?jwt=$token" > $null
}
catch
{
    Write-Log -message ("Unable to login to base-url: $baseUrl " + $_.Exception.Message) -isError $true
    Exit
}

$headers = @{
    'Authorization' = "Bearer $token"
    'accept' = 'application/json'
    'Content-Type' = 'application/json'
}

# Validate CSV Dir
if (!(Test-Path -Path $csvDir -Type Container))
{
    Write-Log -message ("Unable to find csv-dir: " + $csvDir) -isError $true
    Exit
}

# Upload CSV files
foreach ($csvType in $csvTypes)
{
    $csvFile = $myConfig["csv-$csvType"]
    $csvFile = "$csvDir\$csvFile"
    # Validate CSV Dir
    if (!(Test-Path -Path $csvFile -Type Leaf))
    {
        Write-Log -message ("Unable to find csv file: " + $csvFile) -isError $true
        Exit
    }

    try
    {
        Send-Csv -baseUrl $baseUrl -headers $headers -csvType $csvType -csvFile $csvFile
        Write-Log -message ($csvFile + ' uploaded.') -isError $false
    }
    catch
    {
        Write-Log -message ('Error: Failed to upload ' + $csvFile + '. : ' + $_.Exception.Message) -isError $true
        continue;
    }
}
Write-Log -message "== Finish importing CSV files ==" -isError $false
Exit
