#!/bin/bash
# SQL Update Script
# Description:
#       Updates the database schema and values to match the installed version
#       Completes all the updates between the current and new versions
#       For each update release, the update scripts are run in alphanumeric order
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
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

echo "Starting SQL update process";

# Build update version list
rm -f $versionlist;
for release in `find $instdir/updates -maxdepth 1 -type d | sort -V | cut -c$baselength-1000`;
do
    echo $release >> $versionlist;
    newver=$release;
done

if [ ! -f $conffile ];
then
    echo "New installation or conf file missing";
    echo "No cache directories cleaned";
    exit;
fi

echo "Using db credentials from: "`readlink -f $conffile`;

# Collect Credentials
sqluser=$(schoolbox-conf --conf="$conffile" --option=SQL_user);
sqlpasswd=$(schoolbox-conf --conf="$conffile" --option=SQL_passwd);
sqlhost=$(schoolbox-conf --conf="$conffile" --option=SQL_host);
sqldb=$(schoolbox-conf --conf="$conffile" --option=SQL_db);

echo "Checking for SQL updates";

# Collect current SQL version
sqlver=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2`;

if [ -z $sqlver ];
then
    echo "Unable to collect current database version. Please check your database credentials.";
    exit;
fi

backupdir=`mysql -u $sqluser -p$sqlpasswd -h $sqlhost $sqldb -e "SELECT value FROM config WHERE name='file_dir' \G;" | grep 'value' | cut -d' ' -f2`;
backupdir=`readlink -f $backupdir/../`
if [ "$backupdir" == "" ];
then
    backupdir="/tmp";
fi

backupfile="$backupdir/$sqlver-`date +%Y-%m-%d_%H-%M-%S`.sql"

echo "Current SQL Version: "$sqlver;
echo "New SQL Version: "$newver;

if [ $sqlver == $newver ];
then
    echo "No updates required";
    exit;
fi

# Compare two updates using version sort
# Returns:
#   0 if $1 = $2
#   1 if $1 < $2
#   2 if $1 > $2
versionCompare () {
    # Validate we got 2 function args
    if [[ $# != 2 ]];
    then
        echo "Invalid arg count. Required: 2. Found: $#";
        exit 1;
    fi

    # Are args equal?
    if [[ $1 == $2 ]]
    then
        return 0
    fi

    # Which arg is first according to version sort?
    if [[ `echo -e "$1\n$2" | sort -V | head -n1` == $1 ]];
    then
        return 1;
    else
        return 2;
    fi;
}

# Determine the first update directory to execute
firstUpdate='';
for release in `cat ${versionlist}`;
do
    versionCompare ${sqlver} ${release};
    if [[ $? == 1 ]];
    then
        firstUpdate=${release};
        break;
    fi;
done

start=`grep -n "$firstUpdate" $versionlist | cut -d':' -f1`;
if [ -z $start ];
then
    echo "Error: SQL version not supported for update, no changes made";
    exit;
fi

end=`grep -n "$newver" $versionlist | cut -d':' -f1`;
if [ -z $end ];
then
    echo "Error: SQL version greater than update, no changes made";
    exit;
fi

if [ $start -gt $end ];
then
    echo "Error: SQL version greater than update, no changes made";
    exit;
fi

# Ensure all required resources are available before update, hard fail if not
$instdir/tools/notif/syscheck.php -v;
if [ $? -ne 0 ];
then
    echo "Error: all system dependencies must be available to start the update"
    exit $?;
fi

# Only run DB backup if not completed in the last hour
existingbackup=$(ls -1 $backupdir/$sqlver-`date +%Y-%m-%d_%H-`*.sql 2>/dev/null);
if [[ (-f $existingbackup) && (! -z $existingbackup) ]];
then
    echo "Backup already completed";
    backupfile=$existingbackup;
else
    echo "Backing up database before changes";
    mkdir -p $backupdir;
    mysqldump --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -u $sqluser -p$sqlpasswd $sqldb > $backupfile 2>&1;
fi

echo "Updating database to new version";
errorint=0;
errormsg='';
# Run update scripts in release order, SQL first then PHP for each release
for release in `sed -n "$start","$end"p $versionlist`;
do
    echo "Running update $release";
    # Only run release upgrade if the scripts_list actually exists
    if [ -f $instdir/updates/$release/scripts_list ];
    then
        # Run SQL update scripts for this release
        for scriptcmd in `egrep "\.php|\.sql" $instdir/updates/$release/scripts_list`
        do
            script="$instdir/updates/$release/$scriptcmd";
            # Check script listed in scripts_list actually exists
            if [ -f $script ];
            then
                echo "[ ]["`date +%Y-%m-%d\ %H:%M:%S`"] Starting script: $script";
                if [[ $scriptcmd == *".sql" ]]
                then
                    mysql -h $sqlhost -u $sqluser -p$sqlpasswd $sqldb < $script;
                    # Check for and report errors
                    if [ $? -ne 0 ];
                    then
                        echo "[*]["`date +%Y-%m-%d\ %H:%M:%S`"] Error running update: $script" >&2;
                        errorint=1;
                        errormsg="$errormsg,$script";
                    else
                        echo "[ ]["`date +%Y-%m-%d\ %H:%M:%S`"] Success running update: $script";
                    fi
                fi
                if [[ $scriptcmd == *".php" ]]
                then
                    scriptpath=${script%/*};
                    cd $scriptpath;
                    phpscript=${scriptcmd##*/};
                    echo $phpscript;
                    php -f $phpscript 2>&1;
                    # Check for and report errors
                    if [ $? -ne 0 ];
                    then
                        cd $curdir;
                        echo "[*]["`date +%Y-%m-%d\ %H:%M:%S`"] Error running update: $script";
                        errorint=1;
                        errormsg="$errormsg,$script";
                    else
                        echo "[ ]["`date +%Y-%m-%d\ %H:%M:%S`"] Success running update: $script";
                    fi
                    cd $curdir;
                fi
            fi
        done

        # Update the config table to show current release version
        mysql -h $sqlhost -u $sqluser -p$sqlpasswd $sqldb -e "UPDATE \`config\` SET value='$release' WHERE name='site_version';";
    fi
done

echo "Backup of database located at: $backupfile";

# Reset facter fact
echo "$package""_update_error=0" > /etc/facter/facts.d/"$package"_update.txt;

if [ $errorint -ne 0 ];
then
    mkdir -p /etc/facter/facts.d/ >/dev/null 2>&1;
    errormsg=`echo $errormsg | cut -d',' -f2-100`;
    echo "$package""_update_error=$errorint" > /etc/facter/facts.d/"$package"_update.txt;
    echo "$package""_update_errormsg=$errormsg" >> /etc/facter/facts.d/"$package"_update.txt;
    echo "SQL updates completed with errors. Please manually fix issues or rollback to pre-update backup." >&2;
else
    echo "SQL updates completed";
fi
