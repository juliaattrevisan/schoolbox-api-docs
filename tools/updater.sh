#!/bin/bash
clear;
### Schoolbox Updater V1.0 by Damian Alexander
## Performs Recursive updates provided there is a sequential path!... Note It will terminate if fails. 
## Will backup at each stage of recursive update to /tmp/ with identifiers.
## Designed for Schoolbox V11.0+
echo -e "<br />SCHOOLBOX UPDATER: V1.0\n\n\n\n";
schoolbox_dir=`cd ../ && pwd`
working_dir="/tmp/update_tmp"
isdir() {
if [ ! -d $working_dir ]; then
echo -e "<br />Creating Working Directory..."
sudo mkdir $working_dir;
errorCheck 1;
else
if [[ $recursive == 1 ]]; then
echo -e "<br />Removing old Working Directory..."
rm -Rf $working_dir;
errorCheck 1;
echo -e "<br />Creating Working Directory..."
mkdir $working_dir;
errorCheck 1;
fi;
fi;
}
errorCheck() {
if [ $? -eq 0 ]; then
		echo "... OK";
	else
		echo "... FAILED";
		
		if [ ${#1} -eq 1 ]; then
			exit;
		fi;
fi;
}
grabIndex() {
echo -e "<br />Grabbing Versions Index..."
wget http://schoolbox.com.au/schoolboxinstaller/versions.txt -O $working_dir/versions.txt;
errorCheck 1;
index="$working_dir/versions.txt";
}

grabVersion() {
echo -e "<br />Grabbing the v$new_ver database update file...";
wget http://schoolbox.com.au/schoolboxinstaller/update_$new_ver.sql -O $working_dir/update_$new_ver.sql
errorCheck 1;
echo -e "<br />Grabbing the v$new_ver core files update archive...";
wget http://schoolbox.com.au/schoolboxinstaller/release_$new_ver.tgz -O $working_dir/release_$new_ver.tgz;
errorCheck 1;
}
listVersions() {
wget http://schoolbox.com.au/schoolboxinstaller/versions.txt
errorCheck 1;
cat $working_dir/versions.txt|cut -d "=" -f1
}
loadConfig() {
confloc=`echo "$schoolbox_dir/www/conf/conf.php"`;
if [ -f $confloc ]; then
	echo -e "<br />Loading Schoolbox Configuration Specific to your Installation..."
        sqluser=`cat $confloc|grep \"SQL_user|grep -v //|cut -d "\"" -f4`;
        sqlpass=`cat $confloc|grep \"SQL_passwd|grep -v //|cut -d "\"" -f4`;
        sqldb=`cat $confloc|grep \"SQL_db|grep -v //|cut -d "\"" -f4`;
else
echo "No Config File Found";
exit;
fi;
}
getCurrentVersion() {
echo -e "<br />Obtaining Current Version..."
cur_ver=`echo "SELECT value FROM config WHERE NAME = 'site_version'"|mysql -u $sqluser --password=$sqlpass $sqldb`;
cur_ver=`echo $cur_ver|cut -d " " -f2`;
echo "... $cur_ver"
}
compareVersions() {
cd $working_dir;
grabIndex;
getCurrentVersion;
cur_ver_id=`cat $index|grep $cur_ver|cut -d ":" -f1`
#echo -e "Your Identifier for this update is: $cur_ver_id";
echo -e "<br \/>Determining if a new version is available...";
new_ver_id=`expr $cur_ver_id + 1`;
new_ver=`cat $index|grep "$new_ver_id:"|cut -d ":" -f2`;
if [[ ${#new_ver} -gt 3 ]]; then
echo "<br \/>... Update to v$new_ver Found! Continuing...";
else
echo "<br \/>Your Version is current($cur_ver) Exiting!";
exit;
fi;
}
backupInstall() {
echo -e "<br />Moving Data Directory out of Installation Directory..."
mv $schoolbox_dir/data $schoolbox_dir/../data
errorCheck;
echo -e "<br />Backing up Installation Directory..."
tar -cvzf /tmp/schoolbox_$cur_ver.tgz $schoolbox_dir >> /dev/null;
errorCheck;
echo -e "<br />Backing up Config File..."
cp $schoolbox_dir/www/conf/conf.php /tmp/schoolbox_config.php >> /dev/null;
errorCheck;
echo -e "<br />Backing up Database..."
mysqldump --user=$sqluser --password=$sqlpass $sqldb >> /tmp/$sqldb.sql
errorCheck;
}
restoreAfterBackup() {
echo -e "<br />Restoring Data Directory..."
mv $schoolbox_dir/../data $schoolbox_dir/data;
errorCheck;
echo -e "<br />Restoring Configuration File..."
mv /tmp/schoolbox_config.php $schoolbox_dir/www/conf/conf.php;
errorCheck;
}
confirmPermissions() {
echo -e "<br />Resetting all Permissions on install Directory..."
chmod -R 775 $schoolbox_dir;
errorCheck;
echo -e "<br />Granting Required Permissions to HTML Purifier..."
chmod -R 777 $schoolbox_dir/include/HTMLPurifier;
errorCheck;
echo -e "<br />Granting Required Permissions to Data Directory..."
chmod -R 777 $schoolbox_dir/data;
errorCheck;
}
updateDatabase() {
echo -e "<br />Updating Database..."
mysql -u $sqluser --password=$sqlpass $sqldb < $working_dir/update_$new_ver.sql; >> /dev/null;
errorCheck;
}
updateFiles(){
echo -e "<br />Removing Old Files...";
rm -Rf $schoolbox_dir/*;
errorCheck;
echo -e "<br />Extracting Files and Updating..."
tar --overwrite --overwrite-dir -xvzf $working_dir/release_$new_ver.tgz -C $schoolbox_dir >> /dev/null;
errorCheck 1;
echo -e "<br />Removing Template Config File..."
rm -Rf $schoolbox_dir/www/conf/conf.php;
errorCheck;
}
runCustomScripts() {
cd $schoolbox_dir/tools/$new_ver/;
echo "<br \/>Searching for Scripts for update to v$new_ver and executing...";
for script in `ls`; do
if [[ `echo $script|grep -v "_executed_"|grep .php$` ]]; then
echo "<br \/>Running PHP Script: $script";
php -f $script >> /dev/null;
errorCheck;
echo "<br \/>Marking $script as executed so will not be repeated..";
mv $script "_executed_$script";
errorCheck;
fi;
if [[ `echo $script|grep -v "_executed_"|grep .sh$` ]]; then
echo "<br \/>Running Shell Script: $script";
./$script >> /dev/null;
errorCheck;
echo "<br \/>Marking $script as executed so will not be repeated..";
mv $script "_executed_$script";
errorCheck;
fi;
done;
echo "<br \/>Custom Scripts Queue Processing Complete...";
}
cleanup(){
echo -e "<br />Update to $new_ver Complete!"
echo -e "<br />Removing all temporary Files..."
rm -Rf $working_dir;
errorCheck;
echo -e "<br />Removing Minifier Cache...";
rm -Rf /tmp/minify*
errorCheck;
}
loadConfig;
isdir;
compareVersions;
grabVersion;
backupInstall;
updateFiles;
updateDatabase;
restoreAfterBackup;
runCustomScripts;
confirmPermissions;
cleanup;
