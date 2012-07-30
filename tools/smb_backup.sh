#!/bin/bash
# This script backs up to a File Share that requires authentication. (Samba Share)
# Damian Alexander, 2011
# Should be placed in /usr/sbin/backup.sh on the destination server
if [ $EUID -ne 0 ]; then
        echo -e "This script requires Administrator Privileges. You must run this with the sudo command or as root.\n";
        exit 1
fi
date=`date +%y``date +%m``date +%d``date +%H``date +%M`
logfile="/var/log/schoolboxbackup_$date.log";

data_dir="/var/www/schoolbox";
sql_db="schoolbox";
sql_pass="tnp879";
sql_user="root";
Backup_Share="//SERVER/SHARE";
Backup_Username="USERNAME";
Backup_Password="PASSWORD";
backup_dir="/tmp/SchoolboxBackup";
errorCheck() {
if [ $? -eq 0 ]; then
echo -e "...OK\n" >> $logfile;
else
echo -e "...FAILED\n" >> $logfile;
fi;
}
beginAndMount() {
if [ -d $backup_dir ]; then
echo -e "Skipping Backup due to one already running!\n" >> $logfile;
exit;
else
echo -e "Creating Mount point (Temp Directory)\n" >> $logfile;
mkdir $backup_dir;
errorCheck;
echo -e "Mounting Backup Location ($Backup_Share)\n" >> $logfile;
mount -t smbfs -o username=$Backup_Username,password=$Backup_Password $Backup_Share $backup_dir
errorCheck;
fi;
}

dumpDatabase() {
echo -e "Dumping Copy of Database to Remote Samba Share ($Backup_Share)\n" >> $logfile;
mysqldump --u=$sql_user --password=$sql_pass $sql_db >> $backup_dir/sqlbackup.$date.sql;
errorCheck;
}
createArchive() {
echo -e "Archiving all Data to Samba Share...\n" >> $logfile;
tar -czvf $backup_dir/backup.$date.tgz $data_dir;
errorCheck;
}
cleanupAndUnmount() {
echo -e "Unmounting Remote Share\n" >> $logfile;
umount -f $backup_dir;
errorCheck;
echo -e "Removing Temporary Directory (mount point)\n" >> $logfile;
rm -Rf $backup_dir;
errorCheck;
}
mail() {
echo -e "Sending Email to support@schoolbox.com.au...\n" >> $logfile;
mail -s "Bialik Backup" support@schoolbox.com.au < $logfile;
errorCheck;
}

beginAndMount;
dumpDatabase;
createArchive;
cleanupAndUnmount;