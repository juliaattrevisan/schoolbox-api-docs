#!/bin/bash
clear;
echo -e "\n##########################################################\n";
echo -e "Schoolbox Base Installation Tool - Copyright 2011 Alaress Pty Ltd \n\n";
echo -e "Version Supported: v11.5.x\n";
echo -e "Operating System Requirements: Ubuntu 10.10 (Maverick) or later, or CentOS 5.8+ \n";
echo -e "\n##########################################################\n";
echo -e "\nWARNING THIS TOOL IS DESIGNED TO RUN ON A BLANK SERVER. \nUSE ON A SERVER ALREADY HOSTING SERVICES AT YOUR OWN RISK\n";
echo -e "\n##########################################################\n";
echo -e "\nType \"I Understand\", excluding the quotes and including correct case to continue: \c" && read understand;
if [[ `echo $understand|grep "I Understand"` ]]; then
echo -e "\nUser Accepted: Proceeding with Installation.\n";
else
echo -e "\n User Cancelled: Terminating.\n";
exit;
fi;

### UBUNTU CONFIGURATION SETTINGS (DEPENDENCIES)

dependencies_ubuntu=( "dos2unix" "mysql-server" "openssh-server" "apache2" "libapache2-mod-auth-kerb" "wget" "php5-mysql" "mysql-client" "php5" "php-pear" "php5-curl" "php5-gd" "php5-imap" "php5-ldap" "php5-sybase" "freetds-common" );
supported_ubuntu="maverick|natty|onoeiric";

### CENTOS CONFIGURATION SETTINGS (DEPENDENCIES)

dependencies_centos=( "dos2unix" );
supported_centos="6.0|5.9|5.8";


errorCheck() {
if [ $? -eq 0 ]; then
                echo -e "... Success\n";
        else
                echo -e "... Failed\n";

                if [ ${#1} -eq 1 ]; then
                        echo -e "Critical Failure, Aborting installation.\nIf any issues persist on your installation contact support@schoolbox.com.au or call 1300 932 338, ext. 1.";
                        exit;
                fi;
fi;
}
depcheck () {
#Makes the installation noninteractive, meaning no user authentication or input is required.
export DEBIAN_FRONTEND=noninteractive
packageName=$1;
### UBUNTU PACKAGE CHECK
if [[ `echo $OS|grep "ubuntu"` ]]; then
info=`dpkg -l $packageName`;
if [[ `echo $info|grep "ii $packageName"` ]]; then
echo -e "Server Package: $packageName is installed.\n";
else
echo -e "Server Package: $packageName is not installed.\n";
echo -e "Attempting Installation of Package: $packageName.\c";
if [[ `echo $OS|grep "ubuntu"` ]]; then
apt-get install --force-yes -y $packageName >> /dev/null;
errorCheck 1;
else
echo "This installer does not know how to perform a package installation for your operating system.\n";
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
echo "This installer does not know how to perform a package installation for your operating system.\n";
fi;
fi;
fi;
}

dependancyChecks() {
echo -e "\n##########################################################\n";

echo -e "Running Dependency Checks for Server packages:\n";
if [[ `echo $OS|grep ubuntu` ]]; then
for package in ${dependencies_ubuntu[*]}; do
depcheck $package;
done;
else
if [[ `echo $OS|grep centos` ]]; then
for package in ${dependencies_centos[*]}; do
depcheck $package;
done;
fi;
fi;
}
configureApache() {
echo -e "\n#########################################################\n";
echo -e "Configuration of Apache Web Server:\n";
if [ ! -s /etc/apache2/sites-available/schoolbox ]; then
echo -e "Please input the external URL without HTTP:// (i.e schoolbox.myschool.com):\c" && read extURL;
echo -e "Please input the internal URL without HTTP:// (i.e schoolbox.local):\c" && read intURL;
echo -e "Writing Schoolbox Configuration to Apache...\n";
echo "NameVirtualHost *" >> /etc/apache2/sites-available/schoolbox;
echo "<VirtualHost *>" >> /etc/apache2/sites-available/schoolbox;
echo "DocumentRoot $schoolbox_dir/www" >> /etc/apache2/sites-available/schoolbox;
echo "ServerName $extURL" >> /etc/apache2/sites-available/schoolbox;
echo "ServerAlias $intURL" >> /etc/apache2/sites-available/schoolbox;
echo "</VirtualHost>" >> /etc/apache2/sites-available/schoolbox;
echo -e "Enabling Site: \c";
a2ensite schoolbox;
errorCheck;
echo -e "Enabling Rewrite Mod for Apache: \c";
a2enmod rewrite;
errorCheck;
else
echo -e "Schoolbox already has an Apache configuration file.\n";
rollback;
fi;
}
checkOS() {
echo -e "\n##########################################################\n";
echo -e "Operating System Detection:\n";
if [ -s /etc/lsb-release ]; then
if [[ `cat /etc/lsb-release|grep "DISTRIB_ID=Ubuntu"` ]]; then
codename=`cat /etc/lsb-release |grep DISTRIB_CODENAME|cut -d "=" -f2`;
release=`cat /etc/lsb-release |grep DISTRIB_RELEASE|cut -d "=" -f2`;
if [[ `cat /etc/lsb-release|egrep "$ubuntu_supported"` ]]; then
echo -e "Detected Ubuntu $release - '$codename'\n";
OS="ubuntu";
else
echo -e "Found Ubuntu $release - '$codename'\nThis version is Unfortunately not supported by this installer.\nPlease contact support@schoolbox.com.au for assistance.\n";
exit;
fi;
fi;
if [ -s /etc/redhat-release ]; then
if [[ `cat /etc/redhat-release|grep "CentOS Linux"` ]]; then
release=`cat /etc/redhat-release|cut -d " " -f4`;
if [[ `echo $release|egrep "$centos_supported" ` ]]; then
echo -e "Detected CentOS $release \n";
OS="centos";
else
echo -e "Found CentOS $release \nThis version is Unfortunately not supported by this installer.\nPlease contact support@schoolbox.com.au for assistance.\n";
exit;
fi;
else
echo -e "\nNo Supported Operating System Detected.\n";
exit;
fi;
fi;
fi;
}
downloadLatest() {
echo -e "\n##########################################################\n";
echo -e "Obtaining Latest Major Version Number:\n"
wget -q http://schoolbox.com.au/schoolboxinstaller/version_index -O /tmp/version_index;
echo -e "Command Output (Ignore): \c";
dos2unix /tmp/version_index;
ver_num=`cat /tmp/version_index|egrep ".5.0|.0.0"|tail -n 1|cut -d ":" -f2;`;
echo -e "Obtaining Latest Major Version Number:\n"
echo -e " v$ver_num\n";
rm -Rf /tmp/version_index;
echo -e "Downloading the v$ver_num Schoolbox Release Package: \c";
wget -q http://schoolbox.com.au/schoolboxinstaller/release_$ver_num.tgz -O /tmp/release.tgz;
errorCheck 1;
}
extractFiles() {
echo -e "\n##########################################################\n";
echo -e "Extracting Files to Location:\n";
echo -e "Please Select the Full path to a Directory that is not at the root level of a Mounted Partition \n(i.e If the partition is mounted at /var/www/data_drive/ make sure the directory is /var/www/data_drive/schoolbox) : \c" && read schoolbox_dir;
if [ ! -d $schoolbox_dir ]; then
echo -e "Directory not found, Creating: \c";
mkdir -p $schoolbox_dir;
errorCheck 1;
fi;
echo -e "Extracting v$ver_num Release Package to \"$schoolbox_dir\":\c";
tar -xzf /tmp/release.tgz -C $schoolbox_dir/;
errorCheck 1;
rm -Rf /tmp/release.tgz;
}
configureDatabase () {
echo -e "\n##########################################################\n";
echo -e "Database Configuration:\n";
echo -e "Please set a password for the MySQL root user: \c" && read sqlrootpass;
mysql -u root mysql -e "UPDATE user SET password = PASSWORD('$sqlrootpass') where user='root';flush privileges;";
errorCheck 1;
echo -e "Creating Schoolbox Database:\c ";
mysql -u root --password=$sqlrootpass -e "CREATE DATABASE schoolbox;";
errorCheck 1;
echo -e "Enter a Password for the Schoolbox Database User: \c" && read sqlschoolboxpass;
echo -e "Creating Schoolbox Database User: \c";
mysql -u root --password=$sqlrootpass -e "CREATE USER 'schoolbox'@'localhost' IDENTIFIED BY '$sqlschoolboxpass';";
errorCheck 1;
echo -e "Granting Privileges to Schoolbox database to Schoolbox Database User:\c ";
mysql -u root --password=$sqlrootpass -e "GRANT ALL PRIVILEGES ON schoolbox.* TO 'schoolbox'@'localhost';";
errorCheck 1;
echo -e "Performing MySQL Server restart:\c";
/etc/init.d/mysql restart >> /dev/null;
errorCheck 1;
echo -e "Obtaining Initial Database Information from Update Server: \c";
wget -q http://schoolbox.com.au/schoolboxinstaller/initialDatabase.sql -O $schoolbox_dir/sql/initialDatabase.sql;
errorCheck 1;
echo -e "Loading Initial Database Information into Schoolbox Database: \c";
mysql -u root --password=$sqlrootpass schoolbox < $schoolbox_dir/sql/initialDatabase.sql;
errorCheck 1;
echo -e "Setting Paths in Database: \c";
mysql -u root --password=$sqlrootpass schoolbox -e "UPDATE config SET VALUE = '$schoolbox_dir/templates/' WHERE NAME = 'template_dir'; UPDATE config SET VALUE = '$schoolbox_dir/data/files' WHERE NAME = 'file_dir'; UPDATE config SET VALUE = '$schoolbox_dir/data/myFiles' WHERE NAME = 'myfile_dir'; UPDATE config SET VALUE = '$schoolbox_dir/data/submissions' WHERE NAME = 'submission_dir'; UPDATE config SET VALUE = '$schoolbox_dir/data/returns' WHERE NAME = 'returns_dir'; UPDATE config SET VALUE = '$schoolbox_dir/data/dropbox' WHERE NAME = 'dropbox_dir';UPDATE config SET VALUE = '$schoolbox_dir/data/zip' WHERE NAME = 'zip_dir'; UPDATE config SET VALUE = '$schoolbox_dir/data/thumbnails' WHERE NAME = 'zip_dir'; UPDATE config SET VALUE = '$schoolbox_dir/data/cache/xml' WHERE NAME = 'xml_dir';";
errorCheck 1;
}
setConfig () {
echo -e "\n##########################################################\n";
echo -e "Setting Configuration File Settings:\n";
echo -e "Command Output (Ignore): \c";
dos2unix $schoolbox_dir/www/conf/conf.php;
errorCheck 1;
while read line; do
if [[ `echo $line|grep -v "SQL_"` ]]; then
echo -e "$line \n" >> $schoolbox_dir/www/conf/new_conf.php;
fi;
done < $schoolbox_dir/www/conf/conf.php;

echo "define(\"SQL_host\", \"localhost\");" >> $schoolbox_dir/www/conf/new_conf.php;
echo "define(\"SQL_user\", \"schoolbox\");" >> $schoolbox_dir/www/conf/new_conf.php;
echo "define(\"SQL_passwd\", \"$sqlschoolboxpass\");" >> $schoolbox_dir/www/conf/new_conf.php;
echo "define(\"SQL_db\", \"schoolbox\");" >> $schoolbox_dir/www/conf/new_conf.php;
echo "define(\"SQL_dsn\", \"mysql:host=\".SQL_host.\";dbname=\".SQL_db);" >> $schoolbox_dir/www/conf/new_conf.php;
echo -e "Backing up Default configuration file: \c";
cp $schoolbox_dir/www/conf/conf.php $schoolbox_dir/www/conf/conf.back;
errorCheck 1;
echo -e "Enabling New Configuration: \c ";
mv $schoolbox_dir/www/conf/new_conf.php $schoolbox_dir/www/conf/conf.php;
errorCheck 1;
}
installUpdater() {
echo -e "\n##########################################################\n";
echo -e "Installing Updater:\n";
echo "01 01 * * *	root	cd $schoolbox_dir/tools && ./updater.sh >> /var/log/schoolbox_update_log" >> /etc/crontab;
errorCheck;
}
createUser() {
echo -e "\n##########################################################\n";
echo -e "Creating Remote Admin User: \c";
useradd alaress -s /bin/bash -d /home/alaress;
errorCheck;
echo -e "Please Enter a Password for the Remote Admin User below: \n";
passwd alaress
errorCheck;
if [[ ! `cat /etc/group|grep admin` ]]; then
echo -e "Creating Admin Group as it doesnt exist: \c";
groupadd admin;
errorCheck;
fi;
echo -e "Making the Remote Admin User an Administrator of this server: \c";
usermod -g admin alaress;
errorCheck;
}
configurePermissions() {
echo -e "\n##########################################################\n";
echo -e "Configuring Permissions on Directories: \n";
echo -e "Configuring Base Permissions: \c ";
chmod -R 755 $schoolbox_dir;
errorCheck 1;
echo -e "Configuring file Ownership: \c";
chown -R alaress:alaress $schoolbox_dir;
errorCheck 1;
echo -e "Granting Permissions to HTML Purifier:\c";
chmod -R 777 $schoolbox_dir/include/HTMLPurifier;
errorCheck 1;
echo -e "Granting Permissions to Data Directories (Allows Uploading files to Schoolbox): \c";
chmod -R 777 $schoolbox_dir/data;
errorCheck 1;
}
rollback() {
echo -e "\n##########################################################\n";
echo -e "Performing Rollback of Installation: \n";
echo -e "Skip Rollback? (Y/N) - Do this if you ran into another installation: \c" && read skiprollback;
if [[ `echo $skiprollback|egrep "y$|Y$"` ]]; then
exit;
else
echo "User Accepted: Continuing Rollback.\n";
fi;
echo -e "Removing User Account Created:\c";
userdel alaress;
errorCheck;
echo -e "Removing User Account Home Directory\c";
rm -Rf /home/alaress;
errorCheck;
echo -e "Removing Schoolbox Directories:\c";
rm -Rf $schoolbox_dir;
errorCheck;
echo -e "To Drop the MySQL Database type "Yes", including case and not including quotes: \c" && read dropdb;
if [[ `echo $dropdb|grep "Yes$"` ]]; then
echo -e "Executing MySQL Drop Database statement: \c";
mysql -u root --password=$sqlrootpass -e "DROP DATABASE schoolbox;";
errorCheck 1;
fi;
echo -e "To Drop the MySQL User Account Created, type "Yes" including case and not including quotes: \c" && read dropuser;
if [[ `echo $dropuser|grep "Yes$"` ]]; then
echo -e "Executing MySQL Drop user statement: \c";
mysql -u root --password=$sqlrootpass -e "DROP USER  'schoolbox'@'localhost';";
errorCheck 1;
fi;
echo -e "Unsetting the MySQL Root Password:\c";
mysql -u root mysql -e "UPDATE user SET password = NULL where user='root';";
errorCheck 1;
}
restartServices() {
echo -e "Performing MySQL Server restart:\c";
/etc/init.d/mysql restart >> /dev/null;
errorCheck;
echo -e "Performing Apache2 Server restart:\c";
/etc/init.d/apache2 restart >> /dev/null;
errorCheck;
}
checkOS;
dependancyChecks;
downloadLatest;
extractFiles;
configureApache;
configureDatabase;
setConfig;
createUser;
installUpdater;
configurePermissions;
restartServices;