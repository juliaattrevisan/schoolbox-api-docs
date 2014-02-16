#!/bin/bash
# Sync/Restore Script
# Description:
#       Allows for loading database and data files from a live install of Schoolbox
#       or from  a backup source as defined in /etc/schoolbox-backup-cifs.conf
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2014 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#
#

versionlist="/tmp/sb_version_list";

filepath=`readlink -f $0`;
fullpath=${filepath%/*};

conffile=`readlink -f $fullpath/../www/conf/conf.php`;

instdir=`readlink -f $fullpath/../`;
baselength=`echo $instdir/updates/ | wc -m`;
package=${instdir##*/};

curdir=`pwd`;

echo "";
echo "Schoolbox Sync/Restore";

if [ ! -f $conffile ];
then
	errmsg="New installation or conf file missing";
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
	tempsqlver=`mysql -u $tempsqluser -p$tempsqlpasswd -h $tempsqlhost $tempsqldb -BNe "SELECT value FROM config WHERE name='site_version'" 2>/dev/null`;
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
	thistemplatedir=`readlink -f $thistemplatedir`
	if [ ! -d $thistemplatedir ];
	then
		errmsg="Template directory '$thistemplatedir' is not a directory";
		error;
	fi
	echo "Template Directory: $thistemplatedir";
fi
thisfiledir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -BNe "SELECT value FROM config WHERE name='file_dir'"`;
if [ -z $thisfiledir ];
then
	errmsg="Unable to detect current data file directory";
	error;
else
	thisdatadir=`readlink -f $thisfiledir/../`;
	if [ ! -d $thisdatadir ];
	then
		errmsg="Data file directory '$thisdatadir' is not a directory";
		error;
	fi
	echo "Data Directory: $thisdatadir";
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
## This install - other DB backups
thisotherdbs=`ls -1 $thisdatadir/*-*-*-*_*-*-*.sql 2>/dev/null`;
echo -e "Local installs \c"
if [ ! -z "$thisotherdbs" ];
then
	for i in $thisotherdbs;
	do
		 echo -e ".\c";
		 echo "[L],[D],Current,`readlink -f $i`" >> $sbrl;
	done;
fi
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
				tempdatadirlist=`ls -1 $tempfiledir/../*-*-*-*_*-*-*.sql 2>/dev/null`;
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
sshlist=`grep 'Host ' ~/.ssh/config | sed 's/Host\ //g'`;
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
					tempdatadirlist=`ssh $host "ls -1 $tempfiledir/../*-*-*-*_*-*-*.sql 2>/dev/null"`;
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

# Provide list of backups to recover from
#TODO (not available from Schoolbox - Live installs for safety!!!)
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
restore=`sed -n "$(echo $restoreoption)p" $sbrl`;
restorepretty=`sed -n "$(echo $restoreoption)p" $sbrlp`;

if [ -z $restore ];
then
	errmsg="That option does not exist";
	error;
fi

# Provide feedback for confirmation of what is to be done based on above questions.
echo -e "\n $restorepretty";
echo -e "\nIs this the correct option? (y/n): \c" && read confirm;
if [ "$confirm" != "y" ];
then
	errmsg="Restore Cancelled";
	error;
fi

echo -e "\nWell you got this far. Congratulations!";
echo -e "\nTODO - actually perform the restore";
# Check if enough space available for the data file restore

# Backup this installs database

# Sync the datafiles, with progress if possible
## Fix data folder structure
## Fix data and template folder permissions

# If data file sync is successfull
## Drop all existing database tables
## Restore specified database
## Fix data directory locations
## Run SQL updates to current install

# Restart apache

# Report details of failure/success

exit;
