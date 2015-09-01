#!/bin/bash
# Backup Rsync Script
# Description:
#       Backup the data_dir using rsync on a fileshare mounted via smb/cifs
#
# Usage:
#       To be run hourly via crontab as root
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

# Use multiple rsync runs instead of tar/gz/split (experimental)
use_rsync=0;

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
processes=`pgrep backup-rsync.sh`;
process=`echo $processes | wc -w`;
if [ $process -gt 1 ];
then
	echo -e "Warning: Script already running\nReported Processses: $process\n$processes" >> $logfile;
	exit 0;
fi

# Mount smb/cifs share if required and perform basic checks
$fullpath/backup-mount.sh;
if [ $? -ne 0 ];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Fileshare failed to mount. Halting backup." >> $logfile;
	exit 1;
fi

if [ $errorcheck -eq 0 ];
then
	mkdir -p $targetdir >> $logfile 2>&1;

	# Backup $data_dir directory
	if [ $backup_data_dir -eq 1 ];
	then
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup of $data_dir" >> $logfile;
		if [ $use_rsync -ne 0 ];
		then
			echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Creating data directory for rsync backup" >> $logfile;
			mkdir -vp $targetdir/data >> $logfile 2>&1;
			echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Starting backup via rsync" >> $logfile;
			nice rsync -rt --exclude=".*" --exclude=*:* --exclude=*.lock --exclude=*_lock* --exclude=cache/zip/* --exclude=cache/submission_upload/* --exclude=cache/stream/* --delete-after `readlink -f $data_dir`/ $targetdir/data/ >> $logfile 2>&1;
			if [ $? -ne 0 ];
			then
				echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Rsync run had errors, umounting share for safety" >> $logfile;
				umount $backup_dir >> $logfile 2>&1;
				exit 1;
			else
				echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Rsync backup complete" >> $logfile;
			fi
		fi
	else
		echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Skipping backup of $data_dir due to override set" >> $logfile;
	fi
fi
exit 0;
