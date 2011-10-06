#!/bin/bash
clear;
### Schoolbox Updater V1.0 by Damian Alexander
## Will only run 1 update from current version to next version (w/o any input, so can be looped until Version is current)
##### PHP MINIMUM REQUIREMENTS
desired_major="5"; ### MAJOR Version, IE 5 in PHP V5.3
desired_minor="3"; ### MINOR Version, IE 3 in PHP v5.3
## Designed for Schoolbox V11.0+
echo -e "SCHOOLBOX UPDATER: V1.0\n\n\n\n";
schoolbox_dir=`cd ../ && pwd`
working_dir="/tmp/update_tmp"
isdir() {
if [ ! -d $working_dir ]; then
echo -e "Creating Working Directory..."
sudo mkdir $working_dir;
errorCheck 1;
else
if [[ $recursive == 1 ]]; then
echo -e "Removing old Working Directory..."
rm -Rf $working_dir;
errorCheck 1;
echo -e "Creating Working Directory..."
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
echo -e "Grabbing Versions Index..."
wget -q http://schoolbox.com.au/schoolboxinstaller/versions.txt -O $working_dir/versions.txt;
errorCheck 1;
index="$working_dir/versions.txt";
}

grabVersion() {
echo -e "Grabbing the v$new_ver database update file...";
wget -q http://schoolbox.com.au/schoolboxinstaller/update_$new_ver.sql -O $working_dir/update_$new_ver.sql
errorCheck 1;
echo -e "Grabbing the v$new_ver core files update archive...";
wget -q http://schoolbox.com.au/schoolboxinstaller/release_$new_ver.tgz -O $working_dir/release_$new_ver.tgz;
errorCheck 1;
}
listVersions() {
wget -q http://schoolbox.com.au/schoolboxinstaller/versions.txt
errorCheck 1;
cat $working_dir/versions.txt|cut -d ":" -f2
}
loadConfig() {
confloc=`echo "$schoolbox_dir/www/conf/conf.php"`;
if [ -f $confloc ]; then
	echo -e "Loading Schoolbox Configuration Specific to your Installation..."
        sqluser=`cat $confloc|grep \"SQL_user|grep -v //|cut -d "\"" -f4`;
        sqlpass=`cat $confloc|grep \"SQL_passwd|grep -v //|cut -d "\"" -f4`;
        sqldb=`cat $confloc|grep \"SQL_db|grep -v //|cut -d "\"" -f4`;
else
echo "No Config File Found";
exit;
fi;
}
getCurrentVersion() {
echo -e "Obtaining Current Version..."
cur_ver=`echo "SELECT value FROM config WHERE NAME = 'site_version'"|mysql -u $sqluser --password=$sqlpass $sqldb`;
cur_ver=`echo $cur_ver|cut -d " " -f2`;
echo "... $cur_ver"
}
setNewVersion() {
echo -e "Setting New Version to $new_ver...";
`echo "UPDATE config SET value = '$new_ver' where name = 'site_version'"|mysql -u $sqluser --password=$sqlpass $sqldb`;
errorCheck;
}
phpVersionCheck() {
echo "Beginning PHP Version Check...";
php_major=`php -v|grep built|cut -d "-" -f1|cut -d " " -f2|cut -d "." -f1`
php_minor=`php -v|grep built|cut -d "-" -f1|cut -d " " -f2|cut -d "." -f2`
desired_major=`expr $desired_major - 1`;
desired_minor=`expr $desired_minor - 1`;
if [[ $php_major -gt $desired_major && $php_minor -gt $desired_minor ]]; then
echo "V$php_major.$php_minor is OK";
else
desired_major=`expr $desired_major + 1`;
desired_minor=`expr $desired_minor + 1`;
echo "... Version Check Failed, v$php_major.$php_minor is NOT OK... Minimum is v$desired_major.$desired_minor";
cleanup;
exit;
fi;
}

compareVersions() {
cd $working_dir;
grabIndex;
getCurrentVersion;
cur_ver_id=`cat $index|grep $cur_ver|cut -d ":" -f1`
#echo -e "Your Identifier for this update is: $cur_ver_id";
echo -e "Determining if a new version is available...";
new_ver_id=`expr $cur_ver_id + 1`;
new_ver=`cat $index|grep "$new_ver_id:"|cut -d ":" -f2`;
if [[ ${#new_ver} -gt 3 ]]; then
echo "... Update to v$new_ver Found! Continuing...";
else
echo "Your Version is current($cur_ver) or you do not have any access to any new updates Exiting!";
exit;
fi;
}
backupInstall() {
echo -e "Moving Data Directory out of Installation Directory..."
mv $schoolbox_dir/data $schoolbox_dir/../data
errorCheck;
echo -e "Backing up Installation Directory..."
tar -cvzf /tmp/schoolbox_$cur_ver.tgz $schoolbox_dir >> /dev/null;
errorCheck;
echo -e "Backing up Config File..."
cp $schoolbox_dir/www/conf/conf.php /tmp/schoolbox_config.php >> /dev/null;
errorCheck;
echo -e "Backing up Database..."
mysqldump --user=$sqluser --password=$sqlpass $sqldb >> /tmp/$sqldb.sql
errorCheck;
}
restoreAfterBackup() {
echo -e "Restoring Data Directory..."
mv $schoolbox_dir/../data $schoolbox_dir/data;
errorCheck;
echo -e "Restoring Configuration File..."
mv /tmp/schoolbox_config.php $schoolbox_dir/www/conf/conf.php;
errorCheck;
}
confirmPermissions() {
echo -e "Ensuring Alaress is the Owner user on the Installation...";
chown -R alaress:alaress $schoolbox_dir;
errorCheck;
echo -e "Resetting all Permissions on install Directory..."
chmod -R 775 $schoolbox_dir;
errorCheck;
echo -e "Granting Required Permissions to HTML Purifier..."
chmod -R 777 $schoolbox_dir/include/HTMLPurifier;
errorCheck;
echo -e "Granting Required Permissions to Data Directory..."
chmod -R 777 $schoolbox_dir/data;
errorCheck;
echo -e "Update to $new_ver Complete!"
}
updateDatabase() {
echo -e "Updating Database..."
mysql -f -u $sqluser --password=$sqlpass $sqldb < $working_dir/update_$new_ver.sql; >> /dev/null;
errorCheck;
}
updateFiles(){
echo -e "Removing Old Files...";
rm -Rf $schoolbox_dir/*;
errorCheck;
echo -e "Extracting Files and Updating..."
tar --overwrite --overwrite-dir -xvzf $working_dir/release_$new_ver.tgz -C $schoolbox_dir >> /dev/null;
errorCheck 1;
echo -e "Removing Template Config File..."
rm -Rf $schoolbox_dir/www/conf/conf.php;
errorCheck;
}
runCustomScripts() {
cd $schoolbox_dir/tools/$new_ver/;
echo "Searching for Scripts for update to v$new_ver and executing...";
for script in `ls`; do
if [[ `echo $script|grep -v "_executed_"|grep .php$` ]]; then
echo "Running PHP Script: $script";
php -f $script >> /dev/null;
errorCheck;
echo "Marking $script as executed so will not be repeated..";
mv $script "_executed_$script";
errorCheck;
fi;
if [[ `echo $script|grep -v "_executed_"|grep .sh$` ]]; then
echo "Running Shell Script: $script";
./$script >> /dev/null;
errorCheck;
echo "Marking $script as executed so will not be repeated..";
mv $script "_executed_$script";
errorCheck;
fi;
done;
echo "Custom Scripts Queue Processing Complete...";
}
cleanup(){
echo -e "Removing all temporary Files..."
rm -Rf $working_dir;
errorCheck;
echo -e "Removing Minifier Cache...";
rm -Rf /tmp/minify*
errorCheck;
}
loadConfig;
isdir;
compareVersions;
phpVersionCheck;
grabVersion;
backupInstall;
updateFiles;
updateDatabase;
restoreAfterBackup;
runCustomScripts;
confirmPermissions;
setNewVersion;
cleanup;
