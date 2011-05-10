#!/bin/bash
date=`date +%d``date +%m``date +%y`
data_dir="/var/www/schoolbox";
sql_db="schoolbox";
sql_pass="tnp879";
sql_user="root";
Backup_Share="//192.168.1.69/Alaress";
Backup_Username="damian.alexander";
Backup_Password="Maybe123";
if [[ -d /tmp/SchoolboxBackup ]]; then
umount -f /tmp/SchoolboxBackup
fi;
mkdir /tmp/SchoolboxBackup;
mount -t smbfs -o username=$Backup_Username,password=$Backup_Password $Backup_Share /tmp/SchoolboxBackup;
mysqldump --username=$sql_user --password=$sql_pass $sql_db >> /tmp/SchoolboxBackup/sqlbackup.$date.sql;
tar -czvf /tmp/SchoolboxBackup/backup.$date.tgz $data_dir
