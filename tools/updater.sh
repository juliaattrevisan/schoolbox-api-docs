#!/bin/bash
clear;
### Schoolbox Updater V1.0 by Damian Alexander
## Will only run 1 update from current version to next version (w/o any input, so can be looped until Version is current)
##### PHP MINIMUM REQUIREMENTS
desired_major="5"; ### MAJOR Version, IE 5 in PHP V5.3
desired_minor="3"; ### MINOR Version, IE 3 in PHP v5.3
## Designed for Schoolbox V11.0+
echo -e "SCHOOLBOX UPDATER: V1.0\n\n\n\n\n";
schoolbox_dir=`cd ../ && pwd`
working_dir="/tmp/update_tmp";
isdir() {
if [ ! -d $working_dir ]; then
echo -e "Creating Working Directory...\n";
sudo mkdir $working_dir;
errorCheck 1;
else
if [[ $recursive == 1 ]]; then
echo -e "Removing old Working Directory...\n";
rm -Rf $working_dir;
errorCheck 1;
echo -e "Creating Working Directory...\n";
mkdir $working_dir;
errorCheck 1;
fi;
fi;
}
errorCheck() {
if [ $? -eq 0 ]; then
		echo -e "... OK\n";
	else
		echo -e "... FAILED\n";
		
		if [ ${#1} -eq 1 ]; then
			exit;
		fi;
fi;
}
grabIndex() {
echo -e "Grabbing Versions Index...\n";
wget -q http://schoolbox.com.au/schoolboxinstaller/version_index -O $working_dir/version_index;
errorCheck 1;
index="$working_dir/version_index";
}

grabVersion() {
#echo -e "Grabbing the v$new_ver database update file...\n";
#wget -q http://schoolbox.com.au/schoolboxinstaller/update_$new_ver.sql -O $working_dir/update_$new_ver.sql
#errorCheck 1;
echo -e "Grabbing the v$new_ver core files update archive...\n";
wget -q http://schoolbox.com.au/schoolboxinstaller/release_$new_ver.tgz -O $working_dir/release_$new_ver.tgz;
errorCheck 1;
}
listVersions() {
wget -q http://schoolbox.com.au/schoolboxinstaller/version_index
errorCheck 1;
cat $working_dir/version_index|cut -d ":" -f2
}
loadConfig() {
confloc=`echo -e "$schoolbox_dir/www/conf/conf.php"`;
if [ -f $confloc ]; then
	echo -e "Loading Schoolbox Configuration Specific to your Installation...\n";
        sqluser=`cat $confloc|grep \"SQL_user|grep -v //|cut -d "\"" -f4`;
        sqlpass=`cat $confloc|grep \"SQL_passwd|grep -v //|cut -d "\"" -f4`;
        sqldb=`cat $confloc|grep \"SQL_db|grep -v //|cut -d "\"" -f4`;
else
echo -e "No Config File Found\n";
exit;
fi;
}
getCurrentVersion() {
echo -e "Obtaining Current Version...\n";
cur_ver=`echo -e "SELECT value FROM config WHERE NAME = 'site_version'"|mysql -u $sqluser --password=$sqlpass $sqldb`;
cur_ver=`echo $cur_ver|cut -d " " -f2`;
echo -e "... $cur_ver\n";
}
setNewVersion() {
echo -e "Setting New Version to $new_ver...\n";
`echo -e "UPDATE config SET value = '$new_ver' where name = 'site_version'"|mysql -u $sqluser --password=$sqlpass $sqldb`;
errorCheck;
}
phpVersionCheck() {
echo -e "Beginning PHP Version Check...\n";
php_major=`php -v|grep built|cut -d "-" -f1|cut -d " " -f2|cut -d "." -f1` &> /dev/null;
php_minor=`php -v|grep built|cut -d "-" -f1|cut -d " " -f2|cut -d "." -f2` &> /dev/null;
desired_major=`expr $desired_major - 1`;
desired_minor=`expr $desired_minor - 1`;
if [[ $php_major -gt $desired_major && $php_minor -gt $desired_minor ]]; then
echo -e "V$php_major.$php_minor is OK\n";
else
desired_major=`expr $desired_major + 1`;
desired_minor=`expr $desired_minor + 1`;
echo -e "... Version Check Failed, v$php_major.$php_minor is NOT OK... Minimum is v$desired_major.$desired_minor\n";
cleanup;
exit;
fi;
}

compareVersions() {
cd $working_dir;
grabIndex;
getCurrentVersion;
cur_ver_id=`cat $index|grep $cur_ver|cut -d ":" -f1`
#echo -e "Your Identifier for this update is: $cur_ver_id\n";
echo -e "Determining if a new version is available...\n";
new_ver_id=`expr $cur_ver_id + 1`;
new_ver=`cat $index|grep "$new_ver_id:"|cut -d ":" -f2`;
if [[ ${#new_ver} -gt 3 ]]; then
echo -e "... Update to v$new_ver Found! Continuing...\n";
else
echo -e "Your Version is current($cur_ver) or you do not have any access to any new updates Exiting!\n";
exit;
fi;
}
backupInstall() {
echo -e "Moving Data Directory out of Installation Directory...\n";
mv $schoolbox_dir/data $schoolbox_dir/../data
errorCheck;
echo -e "Backing up Installation Directory...\n";
tar -cvzf /tmp/schoolbox.tgz $schoolbox_dir >> /dev/null;
errorCheck;
echo -e "Backing up Config File...\n";
cp $schoolbox_dir/www/conf/conf.php /tmp/schoolbox_config.php >> /dev/null;
errorCheck;

echo -e "Backing up HTACCESS File...\n";
cp $schoolbox_dir/www/.htaccess /tmp/htaccess.file >> /dev/null;
errorCheck;

echo -e "Backing up Database...\n";
mysqldump --user=$sqluser --password=$sqlpass $sqldb >> /tmp/$sqldb.sql
errorCheck;
}
restoreAfterBackup() {
echo -e "Restoring Data Directory...\n";
mv $schoolbox_dir/../data $schoolbox_dir/;
errorCheck;
echo -e "Restoring Configuration File...\n";
mv /tmp/schoolbox_config.php $schoolbox_dir/www/conf/conf.php;
errorCheck;
echo -e "Restoring HTACCESS File...\n";
cp /tmp/htaccess.file $schoolbox_dir/www/.htaccess  >> /dev/null;
errorCheck;

}
confirmPermissions() {
echo -e "Ensuring Alaress is the Owner user on the Installation...\n";
chown -R alaress:alaress $schoolbox_dir;
errorCheck;
echo -e "Resetting all Permissions on install Directory...\n";
chmod -R 775 $schoolbox_dir;
errorCheck;
echo -e "Granting Required Permissions to HTML Purifier...\n";
chmod -R 777 $schoolbox_dir/include/HTMLPurifier;
errorCheck;
echo -e "Granting Required Permissions to Data Directory...\n";
chmod -R 777 $schoolbox_dir/data;
errorCheck;
echo -e "Update to $new_ver Complete!\n";
}
updateDatabase() {
echo -e "Updating Database...\n";
mysql -f -u $sqluser --password=$sqlpass $sqldb < $working_dir/update_$new_ver.sql; >> /dev/null;
errorCheck;
}
updateFiles(){
echo -e "Removing Old Files...\n";
rm -Rf $schoolbox_dir/*;
errorCheck;
echo -e "Extracting Files and Updating...\n";
tar --overwrite --overwrite-dir -xvzf $working_dir/release_$new_ver.tgz -C $schoolbox_dir >> /dev/null;
errorCheck 1;
echo -e "Removing Template Config File...\n";
rm -Rf $schoolbox_dir/www/conf/conf.php;
errorCheck;
echo -e "Removing Template HTACCESS File...\n";
rm -Rf $schoolbox_dir/www/.htacess;
errorCheck;
echo -e "Removing Template Data Directory...\n";
rm -Rf $schoolbox_dir/data;
errorCheck;
}
runCustomScripts() {
if [ -d $schoolbox_dir/updates/$new_ver ]; then
cd $schoolbox_dir/updates/$new_ver/;
if [ -s ./scripts_list ]; then
echo -e "Reading Script Processing List...\n";
for script in `cat scripts_list`; do
file=`echo $script`;
if [[ `echo $file|grep -v "#"|grep .sh$` ]]; then
echo -e "Running Shell Script: $file\n" &> /tmp/update_$new_ver.errlog;
./$file >> /dev/null;
errorCheck;
fi;
if [[ `echo $file|grep -v "#"|grep .php$` ]]; then
echo -e "Running PHP Script: $file\n";
php -f ./$file >> /dev/null &> /tmp/update_$new_ver.errlog;
errorCheck;
fi;
if [[ `echo $file|grep -v "#"|grep .sql$` ]]; then
echo -e "Running SQL Script: $file\n";
mysql -f -u $sqluser --password=$sqlpass $sqldb < ./$file  >> /dev/null &> /tmp/update_$new_ver.errlog;
errorCheck;
fi;
done;
else
echo -e "No Scripts index found for $new_ver.. Ignoring any existing scripts...\n ";
fi;
else
echo -e "No Custom Scripts Directory Found\n";
fi;
echo -e "Custom Scripts Queue Processing Complete...\n";
}
cleanup(){
echo -e "Removing all temporary Files...\n";
rm -Rf $working_dir;
errorCheck;
echo -e "Removing Minifier Cache...\n";
rm -Rf /tmp/minify*
errorCheck;
}
isErrors() {
if [ -s /tmp/update_$new_ver.errlog ]; then
echo -e "There were errors found during the update,\nSee them in /tmp/update_$new_ver.errlog...\n";
else
echo -e "No errors found...\n";
fi;
}
loadConfig;
isdir;
compareVersions;
phpVersionCheck;
grabVersion;
backupInstall;
updateFiles;
##
#Replaced updateDatabase with the Custom Scripts / SQL functions. (Include the file there)
#updateDatabase;
##
restoreAfterBackup;
runCustomScripts;
confirmPermissions;
setNewVersion;
isErrors;
cleanup;