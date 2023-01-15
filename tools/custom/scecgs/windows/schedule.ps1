# schedule.ps1
# Creates tasks to run importCsv.ps1 and uploadPhotos.ps1 in Task Scheduler.

# Get full path of pwsh (Powershell 7)
$pwshInfo = Get-Command pwsh
$pwshPath = $pwshInfo.Source
# Register task for upload photo
$uploadPhotoAction = New-ScheduledTaskAction -Execute "$pwshPath" -Argument "$PSScriptRoot\uploadPhotos.ps1"
$uploadPhotoPrincipal = New-ScheduledTaskPrincipal -UserID "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskPath 'SchoolBox' -TaskName "UploadPhoto" -Action $uploadPhotoAction -Principal $uploadPhotoPrincipal
# Register task for import csv
$importCsvAction = New-ScheduledTaskAction -Execute "$pwshPath" -Argument "$PSScriptRoot\importCsv.ps1"
$importCsvPrincipal = New-ScheduledTaskPrincipal -UserID "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskPath 'SchoolBox' -TaskName "ImportCSV" -Action $importCsvAction -Principal $importCsvPrincipal
