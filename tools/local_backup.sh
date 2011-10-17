#!/bin/bash
### Script Backs up the Schoolbox install (that this script sits in the tools directory of) to /tmp/
# Damian Alexander, 2011
if [ $EUID -eq 0 ]; then
        echo -e "This script does not run with Administrator Privileges. Do NOT run as root OR with sudo.\n";
        exit 1
fi
diskCheck() {
spaceused=`df -h /tmp/|cut -d " " -f21|grep %|cut -d "%" -f1`
spacefree=`expr 100 - $spaceused`
if [ $spacefree -lt "50" ]; then
echo "Not enough space to perform backup!";
exit;
else
echo "Space OK: $spaceused% used, $spacefree% free"
fi;
}
errorCheck() {
if [ $? -eq 0 ]; then
        echo -e "... OK";
else
        echo -e "... FAILED";
fi;
}
date=`date +%y``date +%m``date +%d``date +%H``date +%M`
confloc=`echo "../www/conf/conf.php"`;
if [ -f $confloc ]; then
        sqluser=`cat $confloc|grep \"SQL_user|grep -v //|cut -d "\"" -f4`;
        sqlpass=`cat $confloc|grep \"SQL_passwd|grep -v //|cut -d "\"" -f4`;
        sqldb=`cat $confloc|grep \"SQL_db|grep -v //|cut -d "\"" -f4`;
else
echo "Config File not found";
exit 1;
fi;
diskCheck;
echo -e "Archiving local copy of Install... (destination: /tmp/)\n";
tar -cvzf /tmp/schoolboxBackup_$sqldb.$date.tgz ../
errorCheck;
echo -e "Backing up Database to /tmp/...\n";
mysqldump -u $sqluser --password=$sqlpass $sqldb > /tmp/schoolboxDatabasebackup_$sqldb.$date.sql;
errorCheck;