#!/bin/bash
# Backup Mount Script
# Description:
#       Attempt to create the mount dir & mounted the backup share via smb/cifs
#       Requires cifs-utils be installed
#
# Usage:
#       To be run as required manually or by other scripts as root
#	Returns exit code 0 on success, 1 on failure
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

#Todays date in ISO-8601 format:
DAY0=`date -I`;

#$copies days ago in ISO-8601 format
DAYX=`date -I -d "$copies days ago"`;

logfileprefix="/var/log/$package-backup";
logfile="$logfileprefix-$DAY0.log";

errorcheck=0;

targetdir=$backup_dir/$DAY0;

# Check if smb/cifs share is mounted and attempt mount if necessary
mount_count=`df | grep $backup_dir | wc -l`;
if [ $mount_count -eq 0 ];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] Fileshare not mounted. Attempting to mount" >> $logfile;
	if [ ! -d $backup_dir ]
	then
		mkdir -p $backup_dir >> $logfile 2>&1;
	fi
	mount -t cifs "$backup_share" $backup_dir -o username=$backup_username,password=$backup_password$backup_extra_options >> $logfile 2>&1;
fi

# If share mounted, check available disk space [>90% WARN, >97% FAIL]
mount_count=`df | grep $backup_dir | wc -l`;
if [[ $mount_count -ne 0 && $errorcheck -eq 0 ]];
then
	echo -e "[`date +%Y-%m-%d\ %H:%M:%S`] CIFS remote share ($backup_share) is mounted" >> $logfile;
	mount_space=`df | grep $backup_dir | awk '{print $(NF-1)}' | cut -d'%' -f1 | sort | uniq`;
	if [ -z $mount_space ];
	then
		echo -e "Error: CIFS remote share not mounted correctly." >> $logfile;
		exit 1;
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
			exit 1;
		fi
	fi
	if [ $mount_space -eq 100 ];
	then
		echo -e "Error: CIFS remote share ($backup_share) reporting $mount_space% usage. Backup to still be attempted." >> $logfile;
	fi
else
	if [ $mount_count -eq 0 ];
	then
		echo -e "Error: CIFS remote share ($backup_share) is unable to mount.\nHalting backup" >> $logfile;
		exit 1;
	fi
fi

exit 0;
