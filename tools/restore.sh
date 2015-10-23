#!/bin/bash
# Schoolbox Restore
# Description:
#       Allows for restoring database and data files from another install or
#       backup of Schoolbox.
#       Backup source as defined in /etc/schoolbox-backup-cifs.conf
#       Remote sources as defined in /root/.ssh/config
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2014 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

instdir=`readlink -f $fullpath/../`;
baselength=`echo $instdir/updates/ | wc -m`;
package=${instdir##*/};

curdir=`pwd`;

echo -e "\nSchoolbox Restore";

if [ ! -f $conffile ];
then
	errmsg="New installation or conf file missing";
	error;
fi

# Find the apache user
apacheuser='';
if [ `which apache2 | wc -l` -ne 0 ];
then
	apacheuser='www-data';
fi
if [ `which httpd | wc -l` -ne 0 ];
then
	apacheuser='apache';
fi
if [ -z $apacheuser ];
then
	errmsg="Unable to detect apache";
	error;
fi

echo -e "\033[38;5;1m";
echo "*************************************************************************";
echo "*****                          WARNING!!!                           *****";
echo "*****         Using this program can wipe your installation.        *****";
echo "*****                PROCEED WITH EXTREME CAUTION!!!                *****";
echo "*************************************************************************";
echo -e "\033[39m";

error() {
	# Display an error message in red then exit with error code
	echo -e "\033[38;5;1m"
	echo -e "Error: $errmsg\n";
	echo -e "\033[39m";
	exit 1;
}

readconf() {
	# Collect Credentials from $tempconfile
	tempsqluser=`grep "\"SQL_user" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
	tempsqlpasswd=`grep "\"SQL_passwd" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
	tempsqlhost=`grep "\"SQL_host" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
	tempsqldb=`grep "\"SQL_db" $tempconffile | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4`;
}

readsqlver() {
	# Retrive the Schoolbox version from MySQL using provided credentials
	tempsqlver=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='site_version'" 2>/dev/null`;
}

readsqlconfig() {
	# Retrive some of the Schoolbox config from MySQL using provided credentials
	tempsqlsitehostname=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='site_hostname'" 2>/dev/null`;
	tempsqlsitedomain=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='site_domain'" 2>/dev/null`;
	tempsqlamqpuser=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='amqp_user'" 2>/dev/null`;
	tempsqlamqppass=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='amqp_pass'" 2>/dev/null`;
	tempsqlamqpvhost=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='amqp_vhost'" 2>/dev/null`;
	tempsqlwebsocketport=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='websocket_port'" 2>/dev/null`;
	tempsqlwebsocketportssl=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='websocket_port_ssl'" 2>/dev/null`;
	tempsqlexternaldbhost=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='external_db_host'" 2>/dev/null`;
	tempsqlexternalportal=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='external_portal'" 2>/dev/null`;
}

setsqlconfig() {
	# Set some of the Schoolbox config from MySQL using provided credentials
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlsitehostname' WHERE name='site_hostname'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlsitedomain' WHERE name='site_domain'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlamqpuser' WHERE name='amqp_user'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlamqppass' WHERE name='amqp_pass'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlamqpvhost' WHERE name='amqp_vhost'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlwebsocketport' WHERE name='websocket_port'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlwebsocketportssl' WHERE name='websocket_port_ssl'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlexternaldbhost' WHERE name='external_db_host'";
	mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "UPDATE config SET value='$tempsqlexternalportal' WHERE name='external_portal'";
}

tempconffile="$conffile";
readconf;

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
echo "Package: $package";
echo "SQL Version: $sqlver";
echo "Install Directory: $instdir";
thistemplatedir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='template_dir'"`;
if [ -z $thistemplatedir ];
then
	errmsg="Unable to detect current template directory";
	error;
else
	if [ -z $thistemplatedir ];
	then
		errmsg="Unable to locate template directory in database";
		error;
	fi
	thistemplatedir=`readlink -f $thistemplatedir`
	if [ ! -d $thistemplatedir ];
	then
		errmsg="Template directory '$thistemplatedir' is not a directory";
		error;
	fi
fi
thisfiledir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='file_dir'"`;
if [ -z $thisfiledir ];
then
	errmsg="Unable to detect current data file directory";
	error;
else
	if [ -z $thisfiledir ];
	then
		errmsg="Unable to locate data directory in database";
		error;
	fi
	thisfiledir=`readlink -f $thisfiledir`;
	if [ ! -d $thisfiledir ];
	then
		errmsg="Data file directory '$thisfiledir' is not a directory";
		error;
	fi
	thisdatadir=${thisfiledir%/*};
	if [ ! -d $thisdatadir ];
	then
		errmsg="Data directory '$thisdatadir' is not a directory";
		error;
	fi
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
if [ $testfilemessage != $testfilemessageorig ];
then
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
searchlist='/etc/schoolbox/conf.php /etc/schoolboxdev/conf.php /vagrant/www/conf/conf.php';
rm -f $sbrl;

## Detect database backups for this install
thisinstall=`ls -1 $thisdatadir/{*-*-*-*_*-*-*.sql,backups/*/*/schoolbox_*-*-*.sql.gz} 2>/dev/null`;
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
	if [ `echo $localinstall | grep -v "$package/" | wc -l` -gt 0 ];
	then
		if [ -f $localinstall ];
		then
			tempconffile=`readlink -f $localinstall`;
			readconf;
			readsqlver;
			tempfiledir=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='file_dir'"`;
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
sshlist=`grep 'Host ' ~/.ssh/config 2>/dev/null | sed 's/Host\ //g'`;
echo -e "\nRemote installs \c";
for host in $sshlist;
do
	ssh -o ConnectTimeout=3 -o ConnectionAttempts=1 $host exit >/dev/null 2>&1;
	if [ $? -eq 0 ];
	then
		for x in $searchlist;
		do
			scp $host:$x /tmp >/dev/null 2>&1;
			if [ $? -eq 0 ];
			then
				tempconffile="/tmp/conf.php"
				readconf;
				tempfiledir=`ssh $host "mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe \"SELECT value FROM config WHERE name='file_dir'\""; 2>/dev/null`
				if [ ! -z $tempfiledir ];
				then
					tempdatadirlist=`ssh $host "ls -1 $tempfiledir/../{*-*-*-*_*-*-*.sql,backups/*/*/schoolbox_*-*-*.sql.gz} 2>/dev/null"`;
					for i in $tempdatadirlist;
					do
						echo -e ".\c";
						checki=`ssh $host "readlink -f $i"`;
						echo "[R],[D],$host,$x,$checki" >> $sbrl;
						echo "[R],[A],$host,$x,$checki" >> $sbrl;
					done
				fi
			fi
		done;
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
echo -e "\n     Restore into '$package' at '$instdir'";
echo -e "\nIs this the correct option? (y/n): \c" && read confirm;
if [ "$confirm" != "y" ];
then
	errmsg="Restore Cancelled";
	error;
fi

# Details of restore source
sourceloc=`echo $restore | cut -d',' -f1`;
sourcetype=`echo $restore | cut -d',' -f2`;
sourcep1=`echo $restore | cut -d',' -f3`;
sourcep2=`echo $restore | cut -d',' -f4`;
sourcep3=`echo $restore | cut -d',' -f5`;

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
		sourcedata=${sourcep2%/*};
		echo -e "\nPerforming Data Transfer";
		rsync -rltHv $sourcedata/ $thisdatadir/ --delete-before  --exclude=cache/zip* --exclude=*.lock;
		$instdir/tools/create-data-dirs.sh $thisdatadir;
		$instdir/tools/set-data-dir-perms.sh $thisdatadir $apacheuser;
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
		rsync -rltHzv -e ssh $sourcep1:$sourcedata/ $thisdatadir/ --delete-before  --exclude=cache/zip*;
		$instdir/tools/create-data-dirs.sh $thisdatadir;
		$instdir/tools/set-data-dir-perms.sh $thisdatadir $apacheuser;
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
readconf;
readsqlconfig;
cd $curdir;

# Backup this installs database
echo -e "\nPerforming backup of current database";
newbackup=`$instdir/tools/backup-database.sh`;
if [ `echo $newbackup | grep 'Backup of database at version' | wc -l` -eq 0 ];
then
	errmsg="An error occured during the database backup";
	error;
fi

# Restore the DB
echo -e "\nStarting restore of the database";
## Backup the current DB config separately
mysqldump --allow-keywords -i -e -q -a --skip-extended-insert -c -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb config > /tmp/$sqldb-config-`date -I`.sql;
## Drop existing DB and reload just config table
mysql -u $sqluser -p$sqlpasswd -h $sqlhost -e "DROP DATABASE $sqldb;";
mysql -u $sqluser -p$sqlpasswd -h $sqlhost -e "CREATE DATABASE $sqldb;";
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
## Restore specified database
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < $sourcedb;
## Fix Collation
$instdir/tools/schema/collation.sh;

# Move data files to correct location if restoring from backup
if [ "$sourceloc" = '[B]' ];
then
	echo -e "\nMoving data files to the correct location";
	sourcefiledir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='file_dir'" | cut -d'/' -f2-100`;
	sourcefiledir=${sourcefiledir%/*};
	mv -f $thisdatadir/restoredata/$sourcefiledir/* $thisdatadir/;
	$instdir/tools/create-data-dirs.sh $thisdatadir;
	$instdir/tools/set-data-dir-perms.sh $thisdatadir $apacheuser;
fi

## Cleanup remotely copied database
if [ "$sourceloc" = '[R]' ];
then
	rm -f $sourcedb >/dev/null 2>&1;
fi

## Fix SQL config paths to data directory
$instdir/tools/generate-dir-sql.sh $thisdatadir `readlink -f $thistemplatedir/../` > /tmp/schoolbox-restore-sql-paths;
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb < /tmp/schoolbox-restore-sql-paths;

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
$instdir/tools/update-sql.sh;

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
titleprefix='';
if [ "$package" = 'vagrant' ];
then
	titleprefix='[Vagrant]';
	updatetitle;
	setmailcatcher;
	setsqlconfig;
fi
if [ "$package" = 'schoolboxdev' ];
then
	titleprefix='[Dev]';
	updatetitle;
	setmailcatcher;
	setsqlconfig;
fi

# Restart apache
echo -e "\nRestarting Apache\c";
if [ `which apache2 | wc -l` -ne 0 ];
then
	/usr/sbin/service apache2 restart >/dev/null 2>&1;
fi
if [ $? -ne 0 ];
then
	errmsg="An error occured restarting apache";
	error;
else
	echo -e " - OK";
fi

# Restart schoolbox services
echo -e "\nRestarting Schoolbox Services";
if [ "$package" = 'schoolboxdev' ];
then
	/usr/sbin/service schoolboxdev-digestd restart >/dev/null 2>&1;
	/usr/sbin/service schoolboxdev-scheduled restart >/dev/null 2>&1;
	/usr/sbin/service schoolboxdev-socketd restart >/dev/null 2>&1;
	/usr/sbin/service schoolboxdev-rerouted restart >/dev/null 2>&1;
else
	/usr/sbin/service schoolbox-digestd restart >/dev/null 2>&1;
	/usr/sbin/service schoolbox-scheduled restart >/dev/null 2>&1;
	/usr/sbin/service schoolbox-socketd restart >/dev/null 2>&1;
	/usr/sbin/service schoolbox-rerouted restart >/dev/null 2>&1;
fi

## Run Notifications Binder so RabbitMQ has all the latest bindings for the new data
echo -e "\nRunning Notifications Binder";
php $instdir/tools/mq/binder.php -vvv 2>/dev/null;

# Cleanup
umount $backup_dir 2>/dev/null;

echo -e "\nManual Task Reminders:";
echo -e "* check all the following config settings in the config table";
mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT name,value FROM config WHERE name in ('site_hostname','site_domain','external_db_host','external_portal','websocket_port','websocket_port_ssl','amqp_vhost','amqp_user','amqp_pass','title')";
echo "";

exit;
