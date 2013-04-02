#!/bin/bash
clear;
### Schoolbox Minor Release Updater V1.1 by Alaress
## Will only run 1 update from current version to next version (w/o any input, so can be looped until Version is current)
# Basic Dependencies Check
##### PHP MINIMUM REQUIREMENTS
desired_major="5"; ### MAJOR Version, IE 5 in PHP V5.3
desired_minor="3"; ### MINOR Version, IE 3 in PHP v5.3
## Designed for Schoolbox V11.0+
echo -e "Schoolbox Updater for Ubuntu 10.10 or Higher, or CentOS 6.0.\nSchoolbox Version Requirement: v11.5.0 + \n\n\n";
export schoolbox_dir=`cd ../ && pwd`
export working_dir="/tmp/update_tmp";
workingDirectory() {
if [ ! -d $working_dir ]; then
echo -e "Creating Working Directory:\c";
sudo mkdir $working_dir;
errorCheck 1;
else
echo -e "Removing old Working Directory:\c";
rm -Rf $working_dir;
errorCheck 1;
echo -e "Creating Working Directory:\c";
mkdir $working_dir;
errorCheck 1;
fi;
}
errorCheck() {
if [ $? -eq 0 ]; then
                echo -e "... Success\n";
        else
                echo -e "... Failed\n";

                if [ ${#1} -eq 1 ]; then
                        echo -e "Critical Failure, Aborting update.\nIf any issues persist on your installation contact support@schoolbox.com.au or call 1300 932 338, ext. 1.";
                        exit;
                fi;
fi;
}
grabIndex() {
echo -e "Downloading Updated Version Index:\c";
wget -q http://schoolbox.com.au/schoolboxinstaller/version_index -O $working_dir/version_index;
errorCheck 1;
index="$working_dir/version_index";
}
depcheck () {
packageName=$1;
### UBUNTU PACKAGE CHECK
if [[ `echo $OS|grep "ubuntu"` ]]; then
info=`dpkg -l $packageName`;
if [[ `echo $info|grep "ii $pckageName"` ]]; then
echo -e "Server Package: $packageName is installed.\n";
else
echo -e "Server Package: $packageName is not installed.\n";
echo -e "Attempting Installation of Package: $packageName.\c";
if [[ `echo $OS|grep "ubuntu"` ]]; then
apt-get install --force-yes -y $packageName >> /dev/null;
errorCheck 1;
else
echo "This updater does not know how to perform a package installation for your operating system.\n";
fi;
fi;

else
### CENTOS PACKAGE CHECK
if [[ `echo $OS|grep "centos"` ]]; then
info=`yum list installed|grep $packageName|cut -d " " -f1`;

fi;
if [[ `echo $info|grep "$packageName"` ]]; then
echo -e "Server Package: $packageName is installed.\n";
else
echo -e "Server Package: $packageName is not installed.\n";
echo -e "Attempting Installation of Package: $packageName.\c";
if [[ `echo $OS|grep "centos"` ]]; then
yum install -y $packageName >> /dev/null;
errorCheck 1;
else
echo "This updater does not know how to perform a package installation for your operating system.\n";
fi;
fi;
fi;
}

dependancyChecks() {
echo -e "Running Dependency Checks for Server packages:\n";
for package in ${dependencies[*]}; do
depcheck $package;
done;
}

grabVersion() {
echo -e "Update Download: Downloading the Schoolbox v$new_ver update package, please wait\c";
wget -q http://schoolbox.com.au/schoolboxinstaller/release_$new_ver.tgz -O $working_dir/release_$new_ver.tgz;
errorCheck 1;
}

checkOS() {
echo -e "Operating System Detection:\n";
if [[ `cat /etc/lsb-release|grep "DISTRIB_ID=Ubuntu"` ]]; then
codename=`cat /etc/lsb-release |grep DISTRIB_CODENAME|cut -d "=" -f2`;
release=`cat /etc/lsb-release |grep DISTRIB_RELEASE|cut -d "=" -f2`;
if [[ `cat /etc/lsb-release|egrep "maverick|oneiric|natty|precise"` ]]; then
echo -e "Detected Ubuntu $release - '$codename'\n";
OS="ubuntu";
else
echo -e "Found Ubuntu $release - '$codename'\nThis version is Unfortunately not supported by this updater.\nPlease contact support@schoolbox.com.au for assistance.\n";
exit;
fi;
fi;
if [ -z $OS ]; then
if [[ `cat /etc/redhat-release|grep "CentOS Linux"` ]]; then
release=`cat /etc/redhat-release|cut -d " " -f4`;
if [[ `echo $release|egrep "6\.0|6\.1|6\.2" ` ]]; then
echo -e "Detected CentOS $release \n";
OS="centos";
else
echo -e "Found CentOS $release \nThis version is Unfortunately not supported by this updater.\nPlease contact support@schoolbox.com.au for assistance.\n";
exit;
fi;
else
echo -e "\nNo Supported Operating System Detected.\n";
exit;
fi;
fi;

}


loadConfig() {
confloc=`echo -e "$schoolbox_dir/www/conf/conf.php"`;
if [ -f $confloc ]; then
        echo -e "Loading Database configuration from the Schoolbox config File:\c";
        sqluser=`cat $confloc|grep \"SQL_user|grep -v //|cut -d "\"" -f4`;
        sqlpass=`cat $confloc|grep \"SQL_passwd|grep -v //|cut -d "\"" -f4`;
        sqldb=`cat $confloc|grep \"SQL_db|grep -v //|cut -d "\"" -f4`;
        echo -e "... Success.\n";
else
echo -e "Unable to Locate Configuration file. \nThis is most likely due to the fact you are not running this Script from the /tools directory in Schoolbox.\n";
exit;
fi;
}
getCurrentVersion() {
echo -e "Version Detection:\n";
echo -e "Obtaining Current Version from Database:\n";
cur_ver=`echo -e "SELECT value FROM config WHERE NAME = 'site_version'"|mysql -u $sqluser --password=$sqlpass $sqldb`;
cur_ver=`echo $cur_ver|cut -d " " -f2`;
echo -e "Schoolbox v$cur_ver Detected.\n";
}
setNewVersion() {
echo -e "Setting New Version to v$new_ver.\c";
`echo -e "UPDATE config SET value = '$new_ver' where name = 'site_version'"|mysql -u $sqluser --password=$sqlpass $sqldb`;
errorCheck;
}
phpVersionCheck() {
echo -e "Beginning PHP Version Check:\n";
php_major=`php -v|grep built|cut -d "-" -f1|cut -d " " -f2|cut -d "." -f1` &> /dev/null;
php_minor=`php -v|grep built|cut -d "-" -f1|cut -d " " -f2|cut -d "." -f2` &> /dev/null;
desired_major=`expr $desired_major - 1`;
desired_minor=`expr $desired_minor - 1`;
if [[ $php_major -gt $desired_major && $php_minor -gt $desired_minor ]]; then
echo -e "Success: PHP v$php_major.$php_minor Detected.\n";
else
desired_major=`expr $desired_major + 1`;
desired_minor=`expr $desired_minor + 1`;
echo -e "Failed: PHP v$php_major.$php_minor Detected.\nMinimum is v$desired_major.$desired_minor.\n";
cleanup;
exit;
fi;
}


compareVersions() {
cd $working_dir;
echo -e "Processing Versions and Locating new Update:\n";
grabIndex;
getCurrentVersion;
cur_ver_id=`grep $cur_ver $index|cut -d ":" -f1`
if [[ -z "$cur_ver_id" ]]; then
echo -e "Detected Version is $cur_ver and is not supported by this updater.\nPlease Contact support@schoolbox.com.au for further assistance.\n";
exit;
else
echo -e "Detected version, v$cur_ver is supported.\nYour identifier for this update is: $cur_ver_id.\n";
fi;
echo -e "Locating new Version (if available):\n"
new_ver_id=`expr $cur_ver_id + 1`;
new_ver=`grep "^$new_ver_id:" $index|cut -d ":" -f2`;
echo -e "Verifying new version name:\n";
echo $new_ver >> $working_dir/tmpversion;
errorCheck;
new_ver=`cat -v $working_dir/tmpversion`;
rm -Rf $working_dir/tmpversion;
if [[ ${#new_ver} -gt 3 ]]; then
if [[ ${#new_ver} -lt 12 ]]; then
echo -e "New Update Found: Version v$new_ver.\n";
else
echo -e "Error with target Version (ID:\"$new_ver_id\") (Version:\"$new_ver\").\n";
exit;
fi;
else
echo -e "Your Version is current or you do not have any access to any new updates.\n";
exit;
fi;
}

backupInstall() {
echo -e "Backup Prior to Update:\n";
echo -e "Moving Data Directory out of Installation Directory:\c";
errorCheck 1;
echo -e "Backing up Installation Directory:\c";
tar -czf /tmp/schoolbox_$sqldb$cur_ver.tgz $schoolbox_dir --exclude="$schoolbox_dir/data" --exclude="$schoolbox_dir/data/*";
errorCheck;
echo -e "Backing up Config File:\c";
cp $schoolbox_dir/www/conf/conf.php /tmp/schoolbox_config.php >> /dev/null;
errorCheck;

echo -e "Backing up Database:\c";
mysqldump --user=$sqluser --password=$sqlpass $sqldb >> /tmp/schoolbox_$sqldb$cur_ver.sql
errorCheck;
}
restoreAfterBackup() {
echo -e "Restoring Configuration File:\c";
cp /tmp/schoolbox_config.php $schoolbox_dir/www/conf/conf.php;
errorCheck;
echo -e "Ensuring .htaccess file does not exist:\c";
rm -f $schoolbox_dir/www/.htaccess;
errorCheck;

}
confirmPermissions() {
echo -e "Configuring Permissions: \n";
echo -e "Installation Directory Owner set to Alaress:\c";
chown -R alaress:alaress $schoolbox_dir;
errorCheck;
echo -e "Permissions reset on install Directory:\c";
chmod -R 775 $schoolbox_dir;
errorCheck;
echo -e "Granting Required Permissions to HTML Purifier:\c";
chmod -R 777 $schoolbox_dir/include/HTMLPurifier;
errorCheck;
echo -e "Granting Required Permissions to Data Directory:\c";
chmod -R 777 $schoolbox_dir/data;
errorCheck;
echo -e "Schoolbox v$new_ver Update Completed.\n";
}
updateFiles(){
echo -e "Updating Application Files:\n";
echo -e "Removing Old Files: \c";
errorCheck 1;
echo -e "Extracting and Updating Files:\c";
tar --overwrite --overwrite-dir -xvzf $working_dir/release_$new_ver.tgz -C $schoolbox_dir >> /dev/null;
errorCheck 1;
echo -e "Removing Template Config File:\c";
rm -Rf $schoolbox_dir/www/conf/conf.php;
errorCheck 1;
echo -e "Removing Template HTACCESS File:\c";
rm -Rf $schoolbox_dir/www/.htaccess;
errorCheck 1;
}
runCustomScripts() {
echo -e "Custom Script Processing:\n";
if [ -d $schoolbox_dir/updates/$new_ver ]; then
cd $schoolbox_dir/updates/$new_ver/;
if [ -s ./scripts_list ]; then
echo -e "Reading Script Processing List:\n";
for script in `grep -v "#" scripts_list`; do
file=`echo $script`;
if [[ `echo $file|grep .sh` ]]; then
echo -e "Running Shell Script: $file\c" &> /tmp/update_$dbname$new_ver.errlog;
./$file >> /dev/null;
errorCheck;
fi;
if [[ `echo $file|grep .php` ]]; then
echo -e "Running PHP Script: $file\c";
php -f ./$file >> /dev/null &> /tmp/update_$dbname$new_ver.errlog;
errorCheck;
fi;
if [[ `echo $file|grep .sql` ]]; then
echo -e "Running SQL Script: $file\c";
mysql -f -u $sqluser --password=$sqlpass $sqldb < ./$file  >> /dev/null &> /tmp/update_$dbname$new_ver.errlog;
errorCheck;
fi;
done;
echo -e "Custom Script Processing Complete.\n";
else
echo -e "No scripts processing list found for v$new_ver, Ignoring any scripts that exist.\n ";
fi;
else
echo -e "No custom script directory found.\n";
fi;
}
cleanup(){
echo -e "Cleanup after update:\n";
echo -e "Removing all temporary Files...\c";
rm -Rf $working_dir;
errorCheck;
echo -e "Removing Page Rendering Cache to avoid browser issues:\c";
rm -Rf /tmp/minify*
errorCheck;
}
isErrors() {
echo -e "Error Detection Report: \c";
if [ -s /tmp/update_$dbname$new_ver.errlog ]; then
echo -e "\nThere were potential errors found during the update,\nLocated in: /tmp/update_$dbname$new_ver.errlog.\n";
echo -e "Attempting to mail error Log to support@schoolbox.com.au for review:\c";
mail -s "Schoolbox Update Error Log" support@schoolbox.com.au < /tmp/update_$dbname$new_ver.errlog;
errorCheck;
else
echo -e "No errors found.\n";
fi;
}


#checkOS;
dependancyChecks;
loadConfig;
workingDirectory;
compareVersions;
phpVersionCheck;
grabVersion;
backupInstall;
updateFiles;
restoreAfterBackup;
runCustomScripts;
confirmPermissions;
setNewVersion;
isErrors;
cleanup;

