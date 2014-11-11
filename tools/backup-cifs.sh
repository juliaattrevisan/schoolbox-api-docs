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

# Backup Failure Email Notification address (already sends to support@schoolbox.com.au)
backup_failure_email='';

# Backup of the data directory override - 1 is backup data dir, 0 is skip backup
backup_data_dir=1;

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
	echo -e "Please set the configuration options for this script in: /etc/$package-backup-cifs.conf";
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

# Create facter fact directory if missing
mkdir -p /etc/facter/facts.d >>$logfile 2>&1;

# Check if script already running
processes=`pgrep backup-cifs.sh`;
process=`echo $processes | wc -w`;
if [ $process -gt 1 ];
then
	echo -e "Error: Script already running\nReported Processses: $process\n$processes" >> $logfile;
	echo -e "Error: Script already running\nReported Processses: $process\n$processes";
	errorcheck=1
else
	echo "$package""_last_backup=inprogress" > "/etc/facter/facts.d/$package""_last_backup.txt" 2>/dev/null;
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
	if [ $mount_space -gt 90 ];
	then
		echo -e "Warning: CIFS remote share ($backup_share) reporting $mount_space% usage." >> $logfile;
	fi
	if [ $mount_space -gt 97 ];
	then
		if [ $backup_usage_override -eq 1 ];
		then
			echo -e "Continuing backup as usage override is enabled." >> $logfile;
		else
			echo -e "Error: CIFS remote share ($backup_share) reporting $mount_space% usage. Backup cannot successfully complete." >> $logfile;
			echo -e "Halting backup." >> $logfile;
			errorcheck=1;
		fi
	fi
	if [ $mount_space -eq 100 ];
	then
		echo -e "Error: CIFS remote share ($backup_share) reporting $mount_space% usage. Backup still attempted." >> $logfile;
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
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of MySQL DB" >> $logfile;
	mysqldump --allow-keywords -i -e -q -a -c -u $sql_user -p$sql_pass $sql_db 2>>$logfile > $targetdir/$sql_db-$DAY0.sql;
	if [ $? -ne 0 ];
	then
		echo -e "Error: SQL Backup Failed" >> $logfile;
		if [ $? -ne 1 ];
		then
			echo  $? >> $logfile;
		fi
		
	fi

	# Backup redis db
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of Redis DB" >> $logfile;
	gzip -c /var/lib/redis/dump.rdb > $targetdir/redis-$DAY0.rdb.gz 2>> $logfile;

	# Backup etc directory
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of /etc" >> $logfile;
	tar -czf $targetdir/etc-$DAY0.tar.gz /etc >> $logfile 2>&1;

	# Backup $data_dir directory
	if [ $backup_data_dir -eq 1 ];
	then
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of $data_dir" >> $logfile;
		tar -cz `readlink -f $data_dir` 2>>$logfile | split -a 4 -b 2G -d --verbose - $targetdir/schoolboxdata-$DAY0.tar.gz_ >> $logfile 2>&1;
	else
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Skipping backup of $data_dir due to override set" >> $logfile;
	fi

	# Remove old backup
	if [ -d $backup_dir/$DAYX ]
	then
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting removal of old backup: $backup_dir/$DAYX" >> $logfile;
		rm -rf $backup_dir/$DAYX >> $logfile 2>&1;
	fi

	# Remove backup logfiles older than 30 days
	for oldlogfile in `find $logfileprefix*.log -mtime +30`;
	do
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting removal of old backup logfiles" >> $logfile;
		rm -f $oldlogfile >> $logfile 2>&1
	done

	# Unmount
	umount -f $backup_dir;
fi

# Detect permission denied errors
if [ `grep -i "Permission denied" $logfile | wc -l` -ne 0 ];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Error: permission denied errors detected" >> $logfile;
fi

# On error, email logfile and log fact
if [ `grep -i error $logfile | wc -l` -ne 0 ];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Sending Email to support@schoolbox.com.au" >> $logfile;
	mail -s "[`date +%Y-%m-%d\ %H:%M:%S`] Schoolbox Backup on '$HOSTNAME'" support@schoolbox.com.au < $logfile 2>>$logfile;
	if [ `echo "$backup_failure_email" | grep '@' | wc -l` -eq 1 ];
	then
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Sending Email to $backup_failure_email" >> $logfile;
		mail -s "[`date +%Y-%m-%d\ %H:%M:%S`] Schoolbox Backup on '$HOSTNAME'" $backup_failure_email < $logfile 2>>$logfile;
	fi
	echo "$package""_last_backup=failed" > "/etc/facter/facts.d/$package""_last_backup.txt" 2>/dev/null;
	exit 1;
else
	echo "$package""_last_backup=success" > "/etc/facter/facts.d/$package""_last_backup.txt" 2>/dev/null;
fi

echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Backup Completed" >> $logfile;
