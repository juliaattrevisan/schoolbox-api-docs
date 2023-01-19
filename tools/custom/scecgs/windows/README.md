# Contents of this folder
## config.ini
Configuration for uploading csv files and photo image files:

```
csv-dir=<Path of the folder which has csv files. This folder must exist.>
photo-dir=<Path of the folder which has photo files. The folder must exist in the same folder.>
photo-upload-all=<If `true`, upload all the files in photo-dir.>
token=<Token of a Schoolbox superuser.>
base-url=<Base URL of a Schoolbox instance, e.g. https://demo.schoolbox.com.au.>
csv-student=<The filename of student csv file under the folder set as csv-dir.>
csv-staff=<The filename of staff csv file under the folder set as csv-dir.>
csv-relationship=<The filename of relationship csv file under the folder given as csv-dir.>
csv-parent=<The filename of parent csv file under the folder given as csv-dir.>
```

## importCsv.ps1
Script to Upload CSV profile files to Schoolbox instance and import them to Schoolbox connector tables.

The CSV profile files need be placed in the folder specified in `config.ini`
with the names given in the config file.

## uploadPhotos.ps1
Script to upload photo files to Schoolbox and import them as users' profile images.

All photos uploaded must be named using the ID of the individual (e.g. 10255.jpg)
and placed under the folder specified in `config.ini`.

## schedule.ps1
Script to create tasks to run `importCsv.ps1` and `uploadPhotos.ps1` in Task Scheduler.

# Steps

1. Get Schoolbox API token.
2. Set the token in `config.ini`.
3. Also update the other items in `config.ini`.
4. Install PowerShell 7.x, if not installed yet.
5. Run PowerShell as Administrator, and run the `schedule.ps1` script in this folder.
6. Open Task Scheduler and check the two tasks installed under Schoolbox.
7. In Task Scheduler, manually run the two tasks and check log files created under `logs` folder in this folder.
8. In Task Scheduler, set schedule to run the tasks.

# Notes

The following commands may help, when Windows refuses to run scripts:

