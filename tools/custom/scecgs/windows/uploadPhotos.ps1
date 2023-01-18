# uploadPhotos.ps1
# Uploads photo files to Schoolbox and import them as users' profile images.
# All photos uploaded must be named using the ID of the individual (e.g. 10255.jpg)

# Configuration

# Set base configuration from Parse config.txt file in the same folder with the following content
#
# csv-dir=<The folder name of csv files. This folder must exist in the same folder.>
# photo-dir=<The folder name of photo files. The folder must exist in the same folder.>
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
$photoDir = $myConfig['photo-dir']
# Extension of photo files
$photoExt = '.jpg', '.png'
# Hidden file for the script to check last updated date-time
$lastUpdatePath = "$photoDir\0-last-update"
# Log file
$Global:LogPath = "$PSScriptRoot\logs\upload_photo.log"

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

# Function Send-Photo
#  Upload a photo file to storage and import it as a portrait photo
#  @param string baseUrl Base URL of API endpoint
#  @param array headers Headers for API
#  @param string photoFile Full path of the photo file to import
function Send-Photo
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
    $resultImport = Invoke-RestMethod -Uri $uri -Headers $headers -Method Post -Form $formImport
    if ($resultImport.error.Count) {
        throw $resultImport.error
    }
}

# MAIN
Set-Location $PSScriptRoot
Write-Log -message "== Start uploading photos ==" -isError $false

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

# Validate Photo Dir
if (!(Test-Path -Path $photoDir -Type Container))
{
    Write-Log -message ("Unable to find photo-dir: " + $photoDir) -isError $true
    Exit
}

# Find the file to check the last updated date-time. Create the file, if it doesn't exist
$uploadAll = $true;
try
{
    $systemFiles = Get-ChildItem -Path $lastUpdatePath -Force
    $lastUpdated = $systemFiles[0]
    $uploadAll = $false;
}
catch
{
    $lastUpdated = New-Item -Path $lastUpdatePath -ItemType File
    $lastUpdated.Attributes += 'Hidden'
}

# Get files under the Photo Dir
$photos = Get-ChildItem -Path $photoDir
foreach ($photo in $photos)
{
    # If it's older than the last updated time, skip
    if (!$uploadAll -and ($photo.LastWriteTime -lt $lastUpdated.LastWriteTime))
    {
        continue
    }

    # If the file is not png or jpg, skip
    if (!$photoExt.Contains($photo.Extension))
    {
        continue
    }
    try
    {
        Send-Photo -baseUrl $baseUrl -headers $headers -photoFile $photo.FullName
        Write-Log -message ($photo.Name + ' uploaded.') -isError $false
    }
    catch
    {
        Write-Log -message ('Error: Failed to upload ' + $photo.Name + '. : ' + $_.Exception.Message) -isError $true
        continue;
    }
}
$lastUpdated.LastWriteTime = Get-Date
Write-Log -message "== Finish uploading photos ==" -isError $false
Exit
