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

filepath=$(readlink -f "$0");
fullpath=${filepath%/*};

conffile=$(readlink -f "$fullpath"/../www/conf/conf.php);

instdir=$(readlink -f "$fullpath"/../);
package=${instdir##*/};

curdir=$(pwd);

echo "Starting SQL update process for instance: '$INSTANCE_ID'";

versionlist=$(find "$instdir"/updates -mindepth 1 -maxdepth 1 -type d -printf %f\\n | sort -V)

# Build update version list
for release in $versionlist;
do
    newver=$release;
done

if [ ! -f "$conffile" ];
then
    echo "New installation or conf file missing";
    echo "No cache directories cleaned";
    exit;
fi

echo "Using db credentials from: $(readlink -f "$conffile")";

# Collect Credentials
sqluser=$(schoolbox-conf --conf="$conffile" --option=SQL_user);
sqlpasswd=$(schoolbox-conf --conf="$conffile" --option=SQL_passwd);
sqlhost=$(schoolbox-conf --conf="$conffile" --option=SQL_host);
sqldb=$(schoolbox-conf --conf="$conffile" --option=SQL_db);

echo "Checking for SQL updates";

# Collect current SQL version
sqlver=$(mysql -u "$sqluser" -p"$sqlpasswd" -h "$sqlhost" "$sqldb" -e "SELECT value FROM config WHERE name='site_version' \G;" | grep 'value' | cut -d' ' -f2);

if [ -z "$sqlver" ];
then
    echo "Unable to collect current database version. Please check your database credentials.";
    exit;
fi

if [[ "$(echo -e "20.1.alpha1\n${sqlver}" | sort -V | head -n 1)" != "20.1.alpha1" ]]; then
  # DB schema is < 20.1.alpha1
  backup_dir_name='file_dir'
else
  # DB schema is >= 20.1.alpha1
  backup_dir_name='backup_dir'
fi
backupdir=$(mysql -u "$sqluser" -p"$sqlpasswd" -h "$sqlhost" "$sqldb" -e "SELECT value FROM config WHERE name='$backup_dir_name' \G;" | grep 'value' | cut -d' ' -f2);
backupdir=$(readlink --canonicalize-missing "$backupdir"/../)
if [ "$backupdir" == "" ]; then
    echo "Unable to detect backup dir"
    exit 1;
fi

backupfile="$backupdir/$sqlver-$(date +%Y-%m-%d_%H-%M-%S).sql.gz"

echo "Current SQL Version: $sqlver";
echo "New SQL Version: $newver";

if [ "$sqlver" == "$newver" ];
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
    if [[ $1 == "$2" ]]
    then
        return 0
    fi

    # Which arg is first according to version sort?
    if [[ $(echo -e "$1\n$2" | sort -V | head -n1) == "$1" ]];
    then
        return 1;
    else
        return 2;
    fi;
}

# Determine the first update directory to execute
firstUpdate='';
for release in ${versionlist};
do
    versionCompare "${sqlver}" "${release}";
    if [[ $? == 1 ]];
    then
        firstUpdate=${release};
        break;
    fi;
done

start=$(echo "$versionlist" | grep -n "^${firstUpdate}$" | cut -d':' -f1);
if [ -z "$start" ];
then
    echo "Error: SQL version not supported for update, no changes made";
    exit;
fi

end=$(echo "$versionlist" | grep -n "^${newver}$" | cut -d':' -f1);
if [ -z "$end" ];
then
    echo "Error: SQL version greater than update, no changes made";
    exit;
fi

if [ "$start" -gt "$end" ];
then
    echo "Error: SQL version greater than update, no changes made";
    exit;
fi

# Ensure all required resources are available before update, hard fail if not
"$instdir"/tools/notif/syscheck.php -v;
syscheck_exit_code=$?
if [ $syscheck_exit_code -ne 0 ]; then
    echo "Error: all system dependencies must be available to start the update"
    exit $syscheck_exit_code;
fi

# Skip backup if backup exists for same version and within last hour.
# Need to check for min size because gzip of empty file produces non-empty file.
existingbackup=$(find "$backupdir" -mindepth 1 -maxdepth 1 -name "$sqlver-$(date +%Y-%m-%d_%H-)*.sql.gz" | head -n 1);
if [[ -f $existingbackup ]] && [[ $(wc -c "$existingbackup" | awk '{ print $1 }') -gt 10000 ]]; then
    echo "Backup already completed at '$existingbackup'";
    backupfile=$existingbackup;
else
    echo "Backing up database before changes";
    mkdir -p "$backupdir";
    if ! mysqldump --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -h "$sqlhost" -u "$sqluser" -p"$sqlpasswd" "$sqldb" | gzip > "$backupfile"; then
      echo "Unable to backup database '$sqldb'"
      exit 1
    fi
    # For some reason the above test is not failing when the dump fails. Need to check we actually got data.
    if [[ $(wc -c "$backupfile" | awk '{ print $1 }') -lt 1000 ]]; then
      echo "Unable to backup database '$sqldb'. Dump file seems too small."
      exit 1
    fi
    echo "Database backed up to '$backupfile'";
fi

echo "Updating database to new version";
errorint=0;
errormsg='';
# Run update scripts in release order, SQL first then PHP for each release
for release in $(echo "$versionlist" | sed -n "$start","$end"p );
do
    echo "Running update $release";

    # Run SQL update scripts for this release
    for script in $(find "${instdir}/updates/$release" -type f  | grep -E "(.+\.sql|.+\.php)$" | sort)
    do
        # Check script listed in scripts_list actually exists
        if [ -f "$script" ];
        then
            echo "[ ][$(date +%Y-%m-%d\ %H:%M:%S)] Starting script: $script";
            if [[ $script == *".sql" ]]
            then
                mysql -h "$sqlhost" -u "$sqluser" -p"$sqlpasswd" "$sqldb" < "$script";
                # Check for and report errors
                if [ $? -ne 0 ];
                then
                    echo "[*][$(date +%Y-%m-%d\ %H:%M:%S)] Error running update: $script" >&2;
                    errorint=1;
                    errormsg="$errormsg,$script";
                else
                    echo "[ ][$(date +%Y-%m-%d\ %H:%M:%S)] Success running update: $script";
                fi
            fi
            if [[ $script == *".php" ]]
            then
                scriptpath=${script%/*};
                cd "$scriptpath" || exit 1;
                phpscript=${script##*/};
                echo "$phpscript";
                php -f "$phpscript" 2>&1;
                # Check for and report errors
                if [ $? -ne 0 ];
                then
                    cd "$curdir" || exit 1;
                    echo "[*][$(date +%Y-%m-%d\ %H:%M:%S)] Error running update: $script";
                    errorint=1;
                    errormsg="$errormsg,$script";
                else
                    echo "[ ][$(date +%Y-%m-%d\ %H:%M:%S)] Success running update: $script";
                fi
                cd "$curdir" || exit 1;
            fi
        fi
    done

    # Update the config table to show current release version
    mysql -h "$sqlhost" -u "$sqluser" -p"$sqlpasswd" "$sqldb" -e "UPDATE \`config\` SET value='$release' WHERE name='site_version';";
done

echo "Backup of database located at: $backupfile";

# Reset facter fact
echo "$package""_update_error=0" > /etc/facter/facts.d/"$package"_update.txt;

if [ $errorint -ne 0 ];
then
    mkdir -p /etc/facter/facts.d/ >/dev/null 2>&1;
    errormsg=$(echo "$errormsg" | cut -d',' -f2-100);
    echo "$package""_update_error=$errorint" > /etc/facter/facts.d/"$package"_update.txt;
    echo "$package""_update_errormsg=$errormsg" >> /etc/facter/facts.d/"$package"_update.txt;
    echo "SQL updates completed with errors. Please manually fix issues or rollback to pre-update backup." >&2;
else
    echo "SQL updates completed";
fi
