#!/bin/bash
# Backup Script
# Description:
#       Creates a backup of sql, etc, and data_dir on a remote fileshare mounted via smb/cifs
#       Requires cifs-utils be installed
#
# Usage:
#       To be run nightly via crontab as root
# 
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

################# Config Options ##################
# Please set these config options in /etc/schoolbox-backup-cifs.conf

# Schoolbox Data Directory - e.g. /var/lib/schoolbox or /var/www/schoolbox/data
data_dir='/var/lib/schoolbox';

# Number of backup copies to keep in days, 7 = 7 days. Default = 2;
copies='2';

# MySQL Database
sql_db='schoolbox';

# MySQL Username
sql_user='schoolbox';

# MySQL Password
sql_pass='password';

# SMB/CIFS Remote Fileshare
backup_share='//servername/share/';

# SMB/CIFS Username
backup_username='domain\username';

# SMB/CIFS Password
backup_password='password';

# SMB/CIFS Mount Directory
backup_dir='/mnt/SchoolboxBackup';

# SMB/CIFS Exta Options (Optional) - E.g. ',domain=DomainX,sec=ntlmv2' (must start with comma)
backup_extra_options='';

# Disk usage override - set to 1 to not check SMB/CIFS disk usage before backup
backup_usage_override=0;

############## End of Config Options ##############
########### DO NOT EDIT past this line! ###########

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

if [ -f /etc/$package-backup-cifs.conf ];
then
	source /etc/$package-backup-cifs.conf;
else
	echo -e "Please set the configuration options for this script in /etc/schoolbox-backup-cifs.sh";
	exit 1;
fi

# Ensure running with root privileges
if [ $EUID -ne 0 ];
then
	echo -e "This script requires Administrator Privileges. You must run this with the sudo command or as root.";
	exit 1;
fi

# If copies not set, assume default (2)
if [ -z $copies ];
then
	copies='2';
fi

# If copies not 1 or greater, assume default (2)
if [ $copies -lt 1 ];
then
	copies='2';
fi

#Todays date in ISO-8601 format:
DAY0=`date -I`;

#$copies days ago in ISO-8601 format
DAYX=`date -I -d "$copies days ago"`;

logfileprefix="/var/log/$package-backup";
logfile="$logfileprefix-$DAY0.log";

errorcheck=0;

echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Backup Started" >> $logfile;

targetdir=$backup_dir/$DAY0;

# Check if script already running
processes=`pgrep backup-cifs.sh`;
process=`echo $processes | wc -w`;
if [ $process -gt 1 ];
then
	echo -e "Error: Script already running\nReported Processses: $process\n$processes" >> $logfile;
	echo -e "Error: Script already running\nReported Processses: $process\n$processes";
	errorcheck=1
fi

# Check if smb/cifs share is mounted and attempt mount if necessary
mount_count=`df | grep $backup_dir | wc -l`;
if [ $mount_count -eq 0 ];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`]  Fileshare not mounted. Attempting to mount" >> $logfile;
	if [ ! -d $backup_dir ]
	then
		mkdir -p $backup_dir >> $logfile 2>&1;
	fi
	mount -t cifs $backup_share $backup_dir -o username=$backup_username,password=$backup_password$backup_extra_options >> $logfile 2>&1;
fi

# If share mounted, check available disk space [>90% WARN, >97% FAIL]
mount_count=`df | grep $backup_dir | wc -l`;
if [[ $mount_count -ne 0 && $errorcheck -eq 0 ]];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] CIFS remote share ($backup_share) is mounted" >> $logfile;
	mount_space=`df | grep $backup_dir | awk '{print $5;}' | cut -d'%' -f1`;
	if [ -z $mount_space ];
	then
		echo -e "Error: CIFS remote share not mounted correctly.\nHalting backup." >> $logfile;
		errorcheck=1;
	fi
	if [ $mount_space -gt 97 ];
	then
		echo -e "Error: CIFS remote share ($backup_share) reporting $mount_space% usage. Backup cannot successfully complete." >> $logfile;
		if [ $backup_usage_override -eq 1 ];
		then
			echo -e "Continuing backup as override is enabled." >> $logfile;
		else
			echo -e "Halting backup." >> $logfile;
			errorcheck=1;
		fi
	fi
	if [ $mount_space -gt 90 ];
	then
		echo -e "Warning: CIFS remote share ($backup_share) reporting $mount_space% usage." >> $logfile;
	fi
	# Remove backup directory if already exists
	if [ -d $targetdir ]
	then
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting removal of duplicate backup: $targetdir" >> $logfile;
		rm -rf $targetdir >> $logfile 2>&1;
	fi
else
	if [ $mount_count -eq 0 ];
	then
		echo -e "Error: CIFS remote share ($backup_share) is unable to mount.\nHalting backup" >> $logfile;
		errorcheck=1;
	fi
fi

if [ $errorcheck -eq 0 ];
then
	# Run Backup of {sql,etc,$data_dir}
	mkdir -p $targetdir >> $logfile 2>&1;

	# Backup SQL
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of SQL" >> $logfile;
	mysqldump --allow-keywords -i -e -q -a -c -u $sql_user -p$sql_pass $sql_db 2>>$logfile > $targetdir/$sql_db-$DAY0.sql;
	if [ $? -ne 0 ];
	then
		echo -e "Error: SQL Backup Failed" >> $logfile;
		if [ $? -ne 1 ];
		then
			echo  $? >> $logfile;
		fi
		
	fi

	# Backup etc directory
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of /etc" >> $logfile;
	tar -czf $targetdir/etc-$DAY0.tar.gz /etc >> $logfile 2>&1;

	# Backup $data_dir directory
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of $data_dir" >> $logfile;
	tar -cz `readlink -f $data_dir` 2>>$logfile | split -b 10G -d --verbose - $targetdir/schoolboxdata-$DAY0.tar.gz_ >> $logfile 2>&1;

	# Remove old backup
	if [ -d $backup_dir/$DAYX ]
	then
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting removal of old backup: $backup_dir/$DAYX" >> $logfile;
		rm -rf $backup_dir/$DAYX >> $logfile 2>&1;
	fi

	# Remove old backup logfile
	if [ -f "/var/log/schoolboxbackup_$DAYX.log" ]
	then
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting removal of old backup logfile: $logfileprefix-$DAYX.log" >> $logfile;
		rm -f "$logfileprefix-$DAYX.log" >> $logfile 2>&1;
	fi

	# Unmount
	umount -f $backup_dir;
fi

# On error, email logfile
if [ `grep -i "Permission denied" $logfile | wc -l` -ne 0 ];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Error: permission denied errors detected" >> $logfile;
fi
if [ `grep -i error $logfile | wc -l` -ne 0 ];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Sending Email to support@schoolbox.com.au" >> $logfile;
	mail -s "[`date +%Y-%m-%d\ %H:%M:%S`] Schoolbox Backup on '$HOSTNAME'" support@schoolbox.com.au < $logfile 2>>$logfile;
	exit 1;
fi

echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Backup Completed" >> $logfile;
