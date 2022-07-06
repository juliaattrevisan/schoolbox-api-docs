#!/bin/bash
# Schoolbox Restore
# Description:
#       Allows for restoring database and data files from another install or backup of Schoolbox.
#       Backup source as defined in /etc/schoolbox-backup-cifs.conf
#       Remote sources as defined in /root/.ssh/config
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

# Ensure script run as sudo or root so we can read root's SSH credentials
if [[ $EUID != "0" ]]; then
  echo "Please execute script as sudo or root user";
  exit 1;
fi

# Names of config that should be preserved when performing a restoration
readonly PRESERVED_CONFIG=(
  # Retrieve some of the Schoolbox config from MySQL using provided credentials
  "site_hostname" "site_domain" "site_type" "title"

  # AMQP credentials
  "amqp_user" "amqp_pass" "amqp_vhost"

  # External DB Config
  "external_db_host" "external_db_user" "external_db_passwd" "external_db_db" "external_portal"

  # SAML Config
  "saml_sso_url" "saml_idp_metadata_url" "saml_enabled" "saml_slo_enabled" "saml_slo_url" "saml_logout_url" "saml_private_cert" "saml_public_cert" "saml_idp_certificate"

  # LDAP Config
  "ldap_enable" "ldap_host" "ldap_user" "ldap_pass" "ldap_base" "ldap_host_alt" "ldap_user_alt" "ldap_base_alt" "ldap_username"

  # OTFS config
  "otfs_adapter" "otfs_s3_cred_key" "otfs_s3_cred_secret" "otfs_verify_previous_file"

  # DR S3 backups
  "aws_s3_dr_backup_enabled" "aws_s3_dr_backup_visibility" "aws_s3_dr_sync_status"

  # Others
  "xml_dir" "file_dir"  "api_key"
);

declare -A sql_config=();                # Associative array of config to preserve during restoration. Maps config_name to config_value.
declare alaress_password;                # 'alaress' admin user password
declare destination_site_type;           # site_type of instance we are restoring into

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

instdir=`readlink -f $fullpath/../`;

curdir=`pwd`;

# Outputs the instance_id of the destination. Typically the staging instance.
# If multiple found, prompts user to select one.
get_destination_instance_id(){
  local etc_dir="/etc/schoolbox"
  local ini_file="$etc_dir/instances.ini"
  local instance_list
  instance_list=$(schoolbox-read-instances --ini "$ini_file" --option instance-list)
  if [[ $(echo -e "$instance_list" | wc -l) -gt 1 ]]; then
    echo "Multiple instances found. Please select the instance you wish to restore into:" >&2
    local i
    while true; do
      i=0
      for instance in $instance_list; do
        ((i++))
        echo -n "$i. $instance (" >&2
        hostnames=$(INSTANCE_ID="$instance" schoolbox-read-instances --ini "$ini_file" --option hostnames)
        hostname_idx=0
        for hostname in $hostnames; do
          ((hostname_idx++))
          if [[ $hostname_idx -gt 1 ]]; then
            echo -n "," >&2
          fi
          echo -n "$hostname" >&2
        done
        echo ")" >&2
      done
      read -r -p "Choice (1-$i): " choice
      if [[ $choice -lt 1 ]] || [[ $choice -gt $i ]]; then
        echo "Invalid choice" >&2
      else
        break
      fi
    done
    echo "$instance_list" | sed -n "${choice}p"
  else
    echo "$instance_list"
  fi
}

# Determine which instance to restore into
if [[ -n $INSTANCE_ID ]]; then
  destination_instance_id=$INSTANCE_ID
else
  destination_instance_id="$(get_destination_instance_id)"
fi
if [[ -z "$destination_instance_id" ]]; then
  echo "Unable to derive destination instance_id" >&2
  exit 1
fi

error() {
	# Display an error message in red then exit with error code
	echo -e "\033[38;5;1m"
	echo -e "Error: $errmsg\n";
	echo -e "\033[39m";
	exit 1;
}

echo -e "\nSchoolbox Restore";

if [ ! -f $conffile ];
then
	errmsg="New installation or conf file missing";
	error;
fi

# Find the nginx user
wwwuser='www-data';

echo -e "\033[38;5;1m";
echo "*************************************************************************";
echo "*****                          WARNING!!!                           *****";
echo "*****         Using this program can wipe your installation.        *****";
echo "*****                PROCEED WITH EXTREME CAUTION!!!                *****";
echo "*************************************************************************";
echo -e "\033[39m";



readconf() {
	# Collect Credentials from $tempconfile
	tempsqluser=$(schoolbox-conf --conf="$tempconffile" --option=SQL_user);
	tempsqlpasswd=$(schoolbox-conf --conf="$tempconffile" --option=SQL_passwd);
	tempsqlhost=$(schoolbox-conf --conf="$tempconffile" --option=SQL_host);
	tempsqldb=$(schoolbox-conf --conf="$tempconffile" --option=SQL_db);
}
declare temp_ini_file;

# Reads SQL credentials from ini file
# Selects instance in order of preference:
# 1. As specified explicitly by param
# 2. If only a single instance found in ini file
# 3. If multiple instances in ini file, prompt user to select
# prompt user to select
read_ini() {
  local instance_id=$1

  # Was instance_id not explicitly declared?
  if [[ -z $instance_id ]]; then
    # Collect Credentials from $temp_ini_file
    instance_list=$(schoolbox-read-instances --ini "$temp_ini_file" --option instance-list)
    if [[ $(echo "$instance_list" | wc -l) -gt 1 ]]; then
      # Multiple instances in instances.ini file. Prompt user to select an instance.
      valid_selection=0
      while [[ $valid_selection == 0 ]]; do
        line_idx=0
        for instance_id in $instance_list; do
          ((line_idx++))
          echo -n "$line_idx: $instance_id ("
          hostnames=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --ini "$temp_ini_file" --option hostnames)
          hostname_idx=0
          for hostname in $hostnames; do
            ((hostname_idx++))
            if [[ $hostname_idx -gt 1 ]]; then
              echo -n ","
            fi
            echo -n "$hostname"
          done
          echo ")"
        done
        read -r -p "Choice (1-$line_idx): " instance_id_idx
        if [[ $instance_id_idx -lt 1 ]] || [[ $instance_id_idx -gt $line_idx ]]; then
          echo "Invalid selection, enter a value from (1-$line_idx)" >&2
        else
          valid_selection=1
          instance_id=$(echo "$instance_list" | head -n "$instance_id_idx" | tail -n 1)
        fi
      done
    else
      # Only 1 instance in instances.ini file
      instance_id=$instance_list
    fi
  fi

  tempsqluser=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --ini "$temp_ini_file" --option SQL_user);
  tempsqlpasswd=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --ini "$temp_ini_file" --option SQL_passwd);
  tempsqlhost=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --ini "$temp_ini_file" --option SQL_host);
  tempsqldb=$(INSTANCE_ID="$instance_id" schoolbox-read-instances --ini "$temp_ini_file" --option SQL_db);
}

readsqlver() {
	# Retrive the Schoolbox version from MySQL using provided credentials
	tempsqlver=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='site_version'" 2>/dev/null`;
}

readsqlconfig() {
  local config_value;
  local sql_command;
  for config_name in "${PRESERVED_CONFIG[@]}"; do
    sql_command="SELECT value FROM config WHERE name='$config_name'"
    if ! config_value=$(mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "$sql_command" 2>/dev/null); then
      echo "Unable to fetch value for config '$config_name'" >&2
      exit 1
    fi
    #all config values to base 64 and save in array
    sql_config[$config_name]="$(echo -n $config_value | base64)"
  done
  sql_command="SELECT password FROM user WHERE username = 'alaress' AND deleted_at IS NULL"
  if ! alaress_password=$(mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "$sql_command" 2>/dev/null); then
    echo "Unable to fetch alaress password'" >&2
    exit 1
  fi
}

setsqlconfig() {
  local sql_command;
  for config_name in "${!sql_config[@]}"; do
    #config values are in array as base64 values
    sql_command="UPDATE config SET value=FROM_BASE64('${sql_config[$config_name]}') WHERE name='$config_name'"
    if ! mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "$sql_command"; then
      echo "Unable to set value for config '$config_name' to '${sql_config[$config_name]}'" >&2
    fi
  done
  sql_command="UPDATE user SET password = '$alaress_password' WHERE username = 'alaress' AND deleted_at IS NULL"
  if ! mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "$sql_command"; then
    echo "Unable to set alaress_password'" >&2
  fi

}

truncateTables() {
    mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "TRUNCATE mobile_app_registry";
}

liveCheck() {
  echo "Checking site_type of instance being restored to..."

  temp_ini_file="/etc/schoolbox/instances.ini"
  read_ini "$destination_instance_id"
  destination_site_type=$(mysql -h"$tempsqlhost" -u"$tempsqluser" -p"$tempsqlpasswd" "$tempsqldb" -sse "SELECT value FROM config WHERE name = 'site_type'")

  if [[ $destination_site_type == 'dev' ]]; then
    echo "Looks like it is a vagrant instance. Ok." >&2
  elif [[ $destination_site_type == 'stg' ]]; then
    echo "Looks like it is a STG instance. Ok." >&2
  else
    echo "Destination site_type: '$destination_site_type'" >&2
		if [ "$syncType" != "local" ]; then
      echo -e "\033[0;33m";
			echo "Not a DEV or STG instance. Just in case, pretty sure you don't want to run a sync from the STG to the PRD server. If you really really do, run the restore script with the -local flag to override";
      echo -e "\033[0m";
			exit 1;
		else
			echo "You have chosen to restore a local backup to this server. Proceed with caution";
		fi
	fi
}

#Sets $syncType to "db" or "full" if args provided from cli
setSyncType() {
	#If the first argument is defined, we might be trying to automatically do a db or full sync from latest backup
	if [ "$1" == "" ]; then
			echo "No sync type chosen, requesting user input..."
      syncType="";
		else
			if [ "$1" == "-db" ]; then
				syncType="db";
			elif [ "$1" == "-full" ]; then
				syncType="full";
			elif [ "$1" == "-local" ]; then
				syncType="local";
      else
        echo "Invalid first arg : '$1'"
        exit 1;
			fi
	fi
}

setSyncType $1;
liveCheck;
tempconffile="$conffile";
INSTANCE_ID="$destination_instance_id" readconf;

sqluser="$tempsqluser";
sqlpasswd="$tempsqlpasswd";
sqlhost="$tempsqlhost";
sqldb="$tempsqldb";

# Collect current SQL version
readsqlver;
sqlver="$tempsqlver";
if [ -z $sqlver ];
then
	errmsg="Unable to collect current database version. Please check your database credentials.";
	error;
fi

# Detect this install
# TODO - maybe this should already work on a fresh install with no DB loaded at some point
echo -e "* Details of this install *";
echo "Using db credentials from: "`readlink -f $conffile`;
echo "SQL Version: $sqlver";
echo "Install Directory: $instdir";
conf_backup_dir=$(mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='backup_dir'");
if [ -z "$conf_backup_dir" ]; then
  errmsg="Unable to locate data directory in database";
  error;
fi
conf_backup_dir=$(readlink -f $conf_backup_dir);
if [ ! -d "$conf_backup_dir" ]; then
  errmsg="Backup directory '$conf_backup_dir' is not a directory";
  error;
fi
thisdatadir=${conf_backup_dir%/*};
if [ ! -d "$thisdatadir" ]; then
  errmsg="Data directory '$thisdatadir' is not a directory";
  error;
fi

# Check we have correct access to this install
echo -e "\n* Checking Permissions *";

## Check database permissions
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "CREATE TABLE testpermtest (a INT)";
if [ $? -ne 0 ];
then
	errmsg="Testing database permissions: Unable to create table";
	error;
fi
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "DROP TABLE IF EXISTS testpermtest";
if [ $? -ne 0 ];
then
	errmsg="Testing database permissions: Unable to drop table";
	error;
fi
echo "Database Permissions - OK";

## Check file permissions
testfilemessageorig="Testmessage-`date -I`";
echo "$testfilemessageorig" > $thisdatadir/test.file 2>/dev/null
testfilemessage=`cat $thisdatadir/test.file`;
if [ $testfilemessage != $testfilemessageorig ]; then
	errmsg="Data file permission check failed";
	error;
else
	echo "Data File Check - OK";
fi
rm -f $thisdatadir/test.file >/dev/null 2>&1;

# Detect other local installs
echo -e "\n* Detecting Installs and Backups *";

## Wipe current detected list
sbrl='/tmp/schoolbox-restore-list';
searchlist='/usr/share/schoolbox/conf.php /usr/share/schoolboxdev/conf.php';
rm -f $sbrl;

## Detect database backups for this install
thisinstall="$(ls -1 $thisdatadir/{*-*-*-*_*-*-*.sql,*-*-*-*_*-*-*.sql.gz,backups/*/*/schoolbox_*-*-*.sql.gz} 2> /dev/null)";
echo -e "Local installs \c"
if [ ! -z "$thisinstall" ];
then
	for i in $thisinstall;
	do
		 echo -e ".\c";
		 echo "[L],[D],Current,`readlink -f $i`" >> $sbrl;
	done;
fi

# Detect other local installs
for localinstall in $searchlist;
do
	# Search for local installs, but ignore the current install if listed
	if [ `echo $localinstall | grep -v "schoolbox/" | wc -l` -gt 0 ];
	then
		if [ -f $localinstall ];
		then
			tempconffile=`readlink -f $localinstall`;
			INSTANCE_ID="$destination_instance_id" readconf;
			readsqlver;
			tempfiledir=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='backup_dir'"`;
			if [ ! -z $tempfiledir ];
			then
				tempdatadirlist=`ls -1 $tempfiledir/../{*-*-*-*_*-*-*.sql,backups/*/*/schoolbox_*-*-*.sql.gz} 2>/dev/null`;
				for i in $tempdatadirlist;
				do
					echo -e ".\c";
					echo "[L],[D],$tempconffile,`readlink -f $i`" >> $sbrl;
					echo "[L],[A],$tempconffile,`readlink -f $i`" >> $sbrl;
				done;
			fi
		fi
	fi
done

# Detect other remote installs
# if no remote hosts are found check the following:
# 1. Can you SSH to the host found in /root/.ssh/config
# 2. Can you SCP to the host
# 3. run 'schoolbox_check' to ensure connectivity is as intended.

sshlist=`grep 'Host ' /root/.ssh/config 2>/dev/null | sed 's/Host\ //g'`;
echo -e "\nRemote installs \c";
for host in $sshlist;
do
	ssh -o ConnectTimeout=3 -o ConnectionAttempts=1 $host exit >/dev/null 2>&1;
	if [ $? -eq 0 ];
	then
    # remote may be at current or older arch
    remote_found=0
    # First try current conf arch.
    if scp $host:/etc/schoolbox/instances.ini /tmp >/dev/null 2>&1; then
      remote_found=1
      temp_ini_file="/tmp/instances.ini"
      echo ""
      read_ini
    # fallback to old conf arch.
    elif scp $host:/etc/schoolbox/conf.php /tmp >/dev/null 2>&1; then
      remote_found=1
      tempconffile="/tmp/conf.php"
      readconf;
    fi

    if [[ $remote_found == '1' ]]; then
      sql_ver=$(ssh "$host" "mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe \"SELECT value FROM config WHERE name='site_version'\"" 2>/dev/null);
      if [[  "$(echo -e "20.1.alpha1\n${sql_ver}" | sort -V | head -n 1)" != "20.1.alpha1"  ]]; then
        backup_dir='file_dir';
      else
        backup_dir='backup_dir';
      fi

      tempfiledir=`ssh $host "mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe \"SELECT value FROM config WHERE name='${backup_dir}'\"" 2>/dev/null`
      if [ ! -z $tempfiledir ]; then
        tempdatadirlist=`ssh $host "ls -1 $tempfiledir/../{*-*-*-*_*-*-*.sql,backups/*/*/schoolbox_*-*-*.sql.gz} 2>/dev/null"`;
        for i in $tempdatadirlist; do
          echo -e ".\c";
          checki=`ssh $host "readlink -f $i"`;
          echo "[R],[D],$host,$x,$checki" >> $sbrl;
          echo "[R],[A],$host,$x,$checki" >> $sbrl;
        done
      fi
    fi
	fi
done

# Detect backups
backuplist='/etc/schoolbox-backup-cifs.conf /etc/schoolboxdev-backup-cifs.conf'
echo -e "\nCIFS backups \c";
for backupsource in $backuplist;
do
	if [ -f $backupsource ];
	then
		source $backupsource;
		backup_dir='/mnt/SchoolboxBackupRestore';
		mkdir -p $backup_dir;
		mount -t cifs $backup_share $backup_dir -o username=$backup_username,password=$backup_password$backup_extra_options;
		for cifsbackup in `ls -1 $backup_dir`;
		do
			if [ -d $backup_dir/$cifsbackup ];
			then
				if [ -f $backup_dir/$cifsbackup/schoolbox-$cifsbackup.sql ];
				then
					echo -e ".\c";
					echo "[B],[D],$backupsource,$cifsbackup" >> $sbrl;
					echo "[B],[A],$backupsource,$cifsbackup" >> $sbrl;
				fi
			fi
		done
		umount $backup_dir;
	fi
done

# Provide a pretty list of backups to recover from
if [ "$syncType" == "" ] || [ "$syncType" == "local" ]; then
  echo -e "\n\nPlease select a source to restore from:";
  sbrlp="$sbrl-pretty";
  cat -n $sbrl > $sbrlp;
  sed -i 's/[L]/Local/g' $sbrlp;
  sed -i 's/\[Local\]/\[Local\]\ /g' $sbrlp;
  sed -i 's/[R]/Remote/g' $sbrlp;
  sed -i 's/[B]/Backup/g' $sbrlp;
  sed -i 's/\[D\]/DB\ \ /g' $sbrlp;
  sed -i 's/\[A\]/Full/g' $sbrlp;
  sed -i 's/,/\ -\ /g' $sbrlp;
  sed -i 's/\/etc\/schoolbox\/conf.php/Schoolbox\ Live/g' $sbrlp;
  sed -i 's/\/etc\/schoolboxdev\/conf.php/Schoolbox\ Dev/g' $sbrlp;
  sed -i 's/\/vagrant\/www\/conf\/conf.php/Vagrant\ Install/g' $sbrlp;
  sed -i 's/\/etc\/schoolbox-backup-cifs.conf/Schoolbox\ Live/g' $sbrlp;
  sed -i 's/\/etc\/schoolboxdev-backup-cifs.conf/Schoolbox\ Dev/g' $sbrlp;
  echo "Option  Location   Type   Details";
  cat $sbrlp;
  echo -e "\nOption: \c" && read restoreoption;

  totaloptions=`wc -l $sbrl|cut -d' ' -f1`;
  if [ $restoreoption -gt $totaloptions ];
  then
	  errmsg="Your selected option '$restoreoption' is not available";
	  error;
  fi

  restore=`sed -n "$(echo $restoreoption)p" $sbrl 2>/dev/null`;
  restorepretty=`sed -n "$(echo $restoreoption)p" $sbrlp 2>/dev/null`;

  if [ -z $restore ];
  then
  	errmsg="That option does not exist";
  	error;
  fi

  # Provide feedback for confirmation of what is to be done based on above questions.
  echo -e "\n $restorepretty";
  echo -e "\n     Restore into 'schoolbox' at '$instdir'";
  while true; do
    echo -e "\nIs this the correct option? (y/n): \c" && read -r confirm;
    if [[ $confirm =~ [ynYN] ]]; then
      break;
    else
      echo "Invalid input. Please enter 'y' or 'n'"
    fi
  done

  if [[ ! "$confirm" =~ [yY] ]]; then
  	errmsg="Restore Cancelled";
  	error;
  fi
elif [ "$syncType" == "db" ]; then
  echo "$syncType";
  restore=$(grep "\[D\]" $sbrl | grep daily | tail -n 1);
  echo -e "\n Restoring remote DB backup - $restore";
elif [ "$syncType" == "full" ]; then
  echo "$syncType";
  restore=$(grep "\[A\]" $sbrl | grep daily | tail -n 1);
  echo -e "\n Restoring remote full backup - $restore";
else
  errmsg="Your selected sync type '$syncType' is not available";
  error;
fi

# Details of restore source
sourceloc=`echo $restore | cut -d',' -f1`;
sourcetype=`echo $restore | cut -d',' -f2`;
sourcep1=`echo $restore | cut -d',' -f3`;
sourcep2=`echo $restore | cut -d',' -f4`;
sourcep3=`echo $restore | cut -d',' -f5`;


# Check if a full restore can be done
if [ "$sourcetype" == '[A]' ] && [ "$sourceloc" == '[R]' ]; then
  local_mount=$(findmnt -n -o SOURCE --target "$thisdatadir");
  local_mount_total=$(df -B1 | grep "$local_mount" | awk -F " " '{print $2}');
  local_mount_dir=$(mount | grep "$local_mount" | awk -F " " '{print $3}');
  local_non_sb_used=$(du -sh "$local_mount_dir" --exclude "$thisdatadir" -B1 | cut -f 1);
  localAvailableStorage=$((local_mount_total - local_non_sb_used));
  remote_mount=$(ssh "$host" findmnt -n -o SOURCE --target "$sourcep3");
  remote_mount_used=$(ssh "$host" df -B1 | grep "$remote_mount" | awk -F " " '{print $3}');
  remote_data_dir=${tempfiledir%/*};
  remote_real_data_dir=$(ssh "$host" realpath "$remote_data_dir");
  remote_mount_dir=$(ssh "$host" mount | grep "$remote_mount" | awk -F " " '{print $3}');
  remote_not_sb_used=$(ssh "$host" du -sh "$remote_mount_dir" --exclude "$remote_real_data_dir" -B1 2>/dev/null | cut -f 1);
  remote_cache_used=$(ssh "$host" du -sh "$remote_data_dir/cache/zip" -B1 | cut -f 1);
  remoteBackups=$(ssh "$host" du -sb "$remote_real_data_dir"/backups/ | cut -f 1)
  remoteUsedStorage=$((remote_mount_used - remote_not_sb_used - remote_cache_used - remoteBackups));

  # du/df will report different values because du includes FS data structures (inode tables, super-blocks,
  # partially-filled blocks etc) while df just aggregates file content size. Also du includes deleted files still
  # referenced by processes. We could try and calculate/estimate the difference, but it's complicated
  # (see https://ext4.wiki.kernel.org/index.php/Ext4_Disk_Layout and unreliable).
  # Instead, we'll just apply a rough empirical factor.
  readonly SAFETY_FACTORY=1.07
  remoteUsedStorage=$(echo "$remoteUsedStorage * $SAFETY_FACTORY" | bc | cut -f 1 -d '.')
  if [[ "$localAvailableStorage" == "" ]] || [[ "$remoteUsedStorage" == "" ]]; then
    errmsg="Please check the datapool disk on both hosts, one or both are either full or not available";
    error;
  elif [[ $localAvailableStorage -le $remoteUsedStorage ]]; then
    errmsg="You are trying to restore more data ($remoteUsedStorage bytes) than disk that is available ($localAvailableStorage bytes)";
    error;
  fi
  echo "Full sync space check passed: available - $localAvailableStorage bytes / needed - $remoteUsedStorage bytes";
fi

# Sync the datafiles, with progress if possible
backup_dir='/mnt/SchoolboxBackupRestore';
if [ "$sourceloc" = "[B]" ];
then
	# Source is a CIFS Backup, ensure the share is available
	backupsource="$sourcep1";
	source $backupsource;
	mkdir -p $backup_dir;
	umount $backup_dir 2>/dev/null;
	mount -t cifs $backup_share $backup_dir -o username=$backup_username,password=$backup_password$backup_extra_options;
	if [ ! -f $backup_dir/$sourcep2/schoolbox-$sourcep2.sql ];
	then
		errmsg="Unable to locate the database in the backup";
		umount $backup_dir 2>/dev/null;
		error;
	fi
fi

if [ "$sourcetype" = '[A]' ];
then
	# Full restore, sync all data files, then perform cleanup
	echo -e "\n* Full restore *";
	## Fix data folder structure
	## Fix data and template folder permissions
	sourcedb='';
	if [ "$sourceloc" = '[L]' ];
	then
		# Local Source, set the file location of the db backup
		sourcedb="$sourcep2";
		tmpbackupdb=`echo "$sourcedb" | grep '/backups/' | wc -l`;
		if [ "$tmpbackupdb" == "1" ];
		then
			sourcedata=${sourcedb%/*}'/../../../';
		else
			sourcedata=${sourcedb%/*};
		fi
		echo -e "\nPerforming Data Transfer";
		echo -e "\nCopying data from $sourcedata to $thisdatadir";
		rsync -rltHv $sourcedata/ $thisdatadir/ --delete-before  --exclude=cache/zip* --exclude=*.lock --exclude=backups;
		$instdir/tools/create-data-dirs.sh $thisdatadir;
		$instdir/tools/set-data-dir-perms.sh $thisdatadir $wwwuser;
	fi
	if [ "$sourceloc" = '[R]' ];
	then
		# Remote Source, transfer the db file locally.
		gzipdb=`echo "$sourcep3" | grep '.gz' | wc -l`;
		if [ "$gzipdb" == "1" ];
		then
			sourcedata=${sourcep3%/*}'/../../../';
		else
			sourcedata=${sourcep3%/*};
		fi
		sourcesqlfilename=${sourcep3##*/};
		echo -e "\nTransferring database backup";
		scp $sourcep1:$sourcep3 /tmp >/dev/null 2>&1;
		if [ $? -ne 0 ];
		then
			errmsg="Unable to transfer database backup successfully";
			error;
		fi
		sourcedb="/tmp/$sourcesqlfilename";
		if [ ! -f $sourcedb ];
		then
			errmsg="Unable to find $sourcedb";
			error;
		fi
		echo -e "\nPerforming Data Transfer";
		echo -e "\nCopying data from ssh $sourcep1 : $sourcedata to $thisdatadir";
		rsync -rltHzv -e ssh $sourcep1:$sourcedata/ $thisdatadir/ --delete-before  --exclude=cache/zip* --exclude=backups;
		$instdir/tools/create-data-dirs.sh $thisdatadir;
		$instdir/tools/set-data-dir-perms.sh $thisdatadir $wwwuser;
	fi
	if [ "$sourceloc" = '[B]' ];
	then
		# Backup Source, set the file location of the db backup
		sourcedb="$backup_dir/$sourcep2/schoolbox-$sourcep2.sql";
		echo -e "\nPerforming Data Transfer";
		source $sourcep1;
		if [ -z $thisdatadir ];
		then
			errmsg="Big fail - stopping here as going further will wipe your OS $thisdatadir";
			error;
		fi
		rm $thisdatadir/*/ -fR;
		mkdir -p $thisdatadir/restoredata;
		cd $thisdatadir/restoredata;
		cat $backup_dir/$sourcep2/schoolboxdata-$sourcep2.tar.gz* | tar xzv
		# Data files will be in the data directory under the folder $thisdatadir/restoredata
		# After DB restore we will find the path and move them to the correct location
	fi
else
	# DB only restore, sync the db backup locally
	echo -e "\n* DB only restore *";
	sourcedb='';
	if [ "$sourceloc" = '[L]' ];
	then
		# Local Source, set the file location of the db backup
		sourcedb="$sourcep2";
	fi
	if [ "$sourceloc" = '[R]' ];
	then
		# Remote Source, transfer the db file locally.
		sourcesqlfilename=${sourcep3##*/};
		echo -e "\nTransferring database backup";
		scp $sourcep1:$sourcep3 /tmp >/dev/null 2>&1;
		if [ $? -ne 0 ];
		then
			errmsg="Unable to transfer database backup successfully";
			error;
		fi
		sourcedb="/tmp/$sourcesqlfilename";
		if [ ! -f $sourcedb ];
		then
			errmsg="Unable to find $sourcedb";
			error;
		fi
	fi
	if [ "$sourceloc" = '[B]' ];
	then
		# Backup Source, set the file location of the db backup
		sourcedb="$backup_dir/$sourcep2/schoolbox-$sourcep2.sql";
	fi
fi

if [ -z $sourcedb ];
then
	errmsg="No database backup provided";
	error;
fi

# Re-read the conf file to ensure safety
tempconffile="$conffile";
INSTANCE_ID="$destination_instance_id" readconf;
readsqlconfig;
cd $curdir;

# Backup this installs database
echo -e "\nPerforming backup of current database";
newbackup=$(INSTANCE_ID=$destination_instance_id "$instdir"/tools/backup-database.sh);
if [ `echo $newbackup | grep 'Backup of database at version' | wc -l` -eq 0 ];
then
	errmsg="An error occured during the database backup";
	error;
fi

# Restore the DB
echo -e "\nStarting restore of the database";
## Backup the current DB config separately
if ! mysqldump --allow-keywords --default-character-set=utf8mb4 -i -e -q -a --skip-extended-insert -c -u "$sqluser" -p"$sqlpasswd" -h "$sqlhost" "$sqldb" config > /tmp/"$sqldb"-config-"$(date -I)".sql; then
  echo "Unable to backup config table from current database" >&2
  exit 1
fi
## Drop existing DB and reload just config table
mysql -u $sqluser -p$sqlpasswd -h $sqlhost -e "DROP DATABASE $sqldb;";
mysql -u $sqluser -p$sqlpasswd -h $sqlhost -e "CREATE DATABASE $sqldb CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_general_ci';";
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < /tmp/$sqldb-config-`date -I`.sql;
## Unpack if gzipped
gzipdb=`echo "$sourcedb" | grep '.gz' | wc -l`;
if [ "$gzipdb" == "1" ];
then
	echo "Decompressing database '$sourcedb'";
	gzip -d $sourcedb;
	sourcedb=`echo "$sourcedb" | sed 's/\.gz//g'`;
fi
echo "Restoring database '$sourcedb'";
## Restore specified database (commenting out VIEW DEFINER clause on the fly)
if ! cat $sourcedb | pv -s "$(du -b "$sourcedb" | cut -f 1)" | sed -E "s/^\/\*!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g" | mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb; then
  error "Unable to import database from backup file. Exiting."
fi

# Move data files to correct location if restoring from backup
if [ "$sourceloc" = '[B]' ];
then
	echo -e "\nMoving data files to the correct location";
	sourcefiledir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='backup_dir'" | cut -d'/' -f2-100`;
	sourcefiledir=${sourcefiledir%/*};
	mv -f $thisdatadir/restoredata/$sourcefiledir/* $thisdatadir/;
	$instdir/tools/create-data-dirs.sh $thisdatadir;
	$instdir/tools/set-data-dir-perms.sh $thisdatadir $wwwuser;
fi

## Cleanup remotely copied database
if [ "$sourceloc" = '[R]' ];
then
	rm -f $sourcedb >/dev/null 2>&1;
fi

## We may have just imported an old DB schema, which has configs that don't align to the current script.
## In this case, apply custom fixes.
function restore_pre_v20.1_config_dirs(){
  INSTANCE_ID="$destination_instance_id" readconf;
  readsqlver;
  local sql_ver="$tempsqlver";
  local xml_dir;
  local file_dir;
  if [[  "$(echo -e "20.1.alpha1\n${sql_ver}" | sort -V | head -n 1)" != "20.1.alpha1"  ]]; then
    echo "Restored schema is older than this normalisation script. Trying to restore previous local data_dir values, or set new sane values if previous local values are not available"

    if [[ -n "${sql_config[xml_dir]}" ]]; then
      xml_dir="${sql_config[xml_dir]}"
    else
      xml_dir="$thisdatadir/cache/xml"
    fi;
    mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "UPDATE config SET value='$xml_dir' WHERE name='xml_dir'";

    if [[ -n "${sql_config[file_dir]}" ]]; then
      file_dir="${sql_config[file_dir]}"
    else
      file_dir="$thisdatadir/files"
    fi;
    mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "UPDATE config SET value='$file_dir' WHERE name='file_dir'";
  fi
}
restore_pre_v20.1_config_dirs;

## Fix SQL config paths to data directory
"$instdir"/tools/generate-dir-sql.sh "$thisdatadir" | mysql -u "$sqluser" -p"$sqlpasswd" -h "$sqlhost" "$sqldb"

mysql -h"$sqlhost" -u"$sqluser" -p"$sqlpasswd" "$sqldb" -e "UPDATE config SET value = '/var/log/schoolbox/$destination_instance_id' WHERE name = 'logs_dir'"

echo -e "\nEnsuring data dirs exist on disk"
"$instdir"/tools/site/create-data-dirs.sh --instance "$destination_instance_id"

## Build SQL updates if required
sqlupdates=`ls -1 $instdir/updates/*/scripts_list 2>/dev/null | wc -l`;
if [ $sqlupdates -eq 0 ];
then
	echo -e "\nBuilding SQL updates";
	cd $instdir;
	$instdir/tools/build-sql-update.sh >/dev/null 2>&1;
fi

## Run SQL updates to current install
echo "";
setsqlconfig
set -o pipefail
if ! INSTANCE_ID="$destination_instance_id" "$instdir"/tools/update-sql.sh 2>&1 | tee -a /var/log/schoolbox/"$destination_instance_id"/upgrade.log; then
  exit 1
fi
set +o pipefail

## Change the site title to include [Dev] or [Vagrant] if known
updatetitle () {
	tempsqltitle=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe  "SELECT value FROM config WHERE name='title'" 2>/dev/null`;
	newtitle="$titleprefix $tempsqltitle";
	mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "update config set value=\"$newtitle\" where name='title'";
}
setmailcatcher () {
  mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "UPDATE config SET value='127.0.0.1' WHERE name='mail_smtp_host'";
  mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "UPDATE config SET value='1025' WHERE name='mail_smtp_port'";
  mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "UPDATE config SET value='1' WHERE name='mail_enable'";
}
setdebugstacktrace () {
  mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "UPDATE config SET value='1' WHERE name='debug_show_stack_trace'";
}
titleprefix='';
if [ "$destination_site_type" = 'dev' ]; then
	titleprefix='[Vagrant]';
	updatetitle;
	setmailcatcher;
	setsqlconfig;
	setdebugstacktrace;
	truncateTables;
fi
if [ "$destination_site_type" = 'stg' ]; then
	titleprefix='[Staging]';
	updatetitle;
	setmailcatcher;
	setsqlconfig;
	setdebugstacktrace;
	truncateTables;
fi

# Truncate storage_transform table if it is a DB only sync
if [ "$sourcetype" != '[A]' ];
then
  echo "DB-only restore. Truncating 'storage_transform' table" >&2
  mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "TRUNCATE storage_transform";
fi

# If a shared host, need to truncate etherpad to prevent updates/deletes from staging destroying production data
# (First test is old single-instance arch, second test is new multi-instance arch)
if { [ -f /usr/share/schoolbox/www/index.php ] && [ -f /usr/share/schoolboxdev/www/index.php ]; } || { [ -f /etc/schoolbox/instances.ini ] && [[ $(schoolbox-read-instances --option instance-list | wc -l) -gt 1 ]] ;}; then
  echo "Shared stg/prd host detected, truncating 'etherpad_pad' table";
  mysql -u "$tempsqluser" -p"$tempsqlpasswd" -h "$tempsqlhost" "$tempsqldb" -BNe "TRUNCATE etherpad_pad";
fi

# Restart nginx
echo -e "\nRestarting Nginx\c";
if [ `which nginx | wc -l` -ne 0 ];
then
	/usr/sbin/service nginx restart >/dev/null 2>&1;
fi
if [ $? -ne 0 ];
then
	errmsg="An error occured restarting nginx";
	error;
else
	echo -e " - OK";
fi

# Sync plan-type with instance registry
get_plan_type_from_instance_registry() {
  local -r instance_registry_url="$(schoolbox-instance-registry --url)";
  local -r puppet_cert="$(schoolbox-instance-registry --puppet-cert)";
  local -r api_key="$(schoolbox-instance-registry --api-key)";

  # send API request to IR - /api/hosts/{cert}/instances
  local -r ir_host_instances_json="$(curl \
    --silent \
    "$instance_registry_url/api/v2/host/$puppet_cert/instances?instance_id=$destination_instance_id" \
    -H "Authorization: Bearer $api_key" \
    -H "Content-Type: application/json"
  )";

  echo "$ir_host_instances_json" \
    | jq -r \
      '.ir_host_instances | .[] | .plan_type';
}

plan_type="$(get_plan_type_from_instance_registry)";

echo -e "\nSyncing plan type with instance registry"
echo "Setting plan type to: '$plan_type'";

# Invoke set-plan.php to sync plan with IR
INSTANCE_ID="$destination_instance_id" php "$instdir"/tools/set-plan.php --plan "$plan_type";

# Restart schoolbox services
echo -e "\nRestarting Schoolbox Services for instance: $destination_instance_id";
/usr/share/schoolbox/tools/notif/allrestart "$destination_instance_id"

echo -e "Rebuilding cron jobs (because timezone may be different in new DB). Assuming this host is primary"
schoolboxcloud-rebuild-cronjobs --is-primary-host 1

## Run Notifications Binder so RabbitMQ has all the latest bindings for the new data
echo -e "\nReload the subscription cache";
php "$instdir"/tools/notif/route/refresh-binding-cache.php --instance="$destination_instance_id" -vvv;

# Cleanup
umount $backup_dir 2>/dev/null;

echo -e "\nManual Task Reminders:";
echo -e "* check all the following config settings in the config table";
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT name,value FROM config WHERE name in ('site_hostname','site_domain','external_db_host','external_portal','amqp_vhost','amqp_user','amqp_pass','title')";
echo "";

exit;
