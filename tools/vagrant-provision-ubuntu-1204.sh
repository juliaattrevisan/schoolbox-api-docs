#!/bin/bash
vag="[schoolbox]";
cachedir='/vagrant/vagrantcache';
mkdir -p $cachedir;
cd $cachedir;

# Defaults
download_url="vagrant.alaress-dev.com.au";
demo_data_version="latest"
external_db="synergetic";
external_db_host="192.168.1.20";

# Get options
if [ ! -z $1 ]; then download_url=$1; fi;
if [ ! -z $2 ]; then demo_data_version=$2; fi;
if [ ! -z $3 ]; then external_db=$3; fi;
if [ ! -z $4 ]; then external_db_host=$4; fi;

echo "$vag Setting up vagrant with options:";
echo "$vag Download URL: $download_url";
echo "$vag Demo Data Version: $demo_data_version";
echo "$vag External DB: $external_db";
echo "$vag External DB Host: $external_db_host";

echo "$vag Retrieving latest Demo Data files";
curl http://$download_url/schoolboxdemo-$demo_data_version-sha1.txt > schoolboxdemo-$demo_data_version-sha1.txt 2>/dev/null
if [ $? -eq 0 ];
then
	mv -f schoolboxdemo-$demo_data_version-sha1.txt schoolboxdemo-$demo_data_version-sha1-cached.txt;
	rm -f schoolboxdemo-$demo_data_version-sha1.txt;
else
	echo "$vag Unable to retrive $demo_data_version sha1 list, continuing with current";
fi
if [ ! -f schoolboxdemo-$demo_data_version-sha1-cached.txt ];
then
	echo "$vag Unable to retrieve $demo_data_version demo data. Halting provisioning.";
	exit 1;
fi
while read line;
do
	demofile=`echo "$line" | awk {'print $2'}`;
	demosha1=`echo "$line" | awk {'print $1'}`;
	currentsha1=`sha1sum $demofile 2>/dev/null || echo 'false'`;
	if [ "$line" == "$currentsha1" ];
	then
		echo "$vag No change to $demofile";
	else
		echo "$vag Downloading $demofile";
		curl -L http://vagrant.alaress-dev.com.au/$demofile > $demofile;
	fi
done < schoolboxdemo-$demo_data_version-sha1-cached.txt;

echo "$vag Ensuring MySQL is running";
#service mysql restart > /dev/null 2>&1;
service mysql start > /dev/null 2>&1;

echo "$vag Stopping Apache";
service apache2 stop > /dev/null 2>&1;

echo "$vag Disabling php-fpm";
if [ -f /etc/init.d/php5-fpm ];
then
	service php5-fpm stop;
	rm -f /etc/init.d/php5-fpm;
fi

# Kill all old grunt processes, they're probably watches
echo "$vag Stopping background Grunt processes";
pkill grunt;

echo "$vag Removing current Schoolbox MySQL database";
mysql -u root -ptnp879 -e "drop database if exists schoolbox";

echo "$vag Removing current Schoolbox Data directory";
rm /vagrant/data/* -fR;

echo "$vag Setting up browscap cache";
mkdir -p /vagrant/tools/browscap;
rm -f /vagrant/tools/browscap/browscap.ini;
touch /vagrant/tools/browscap/browscap.ini;
cp -f $cachedir/cache.txt /vagrant/tools/browscap/cache.php;

echo "$vag Loading Schoolbox MySQL Demo data";
mysql -u root -ptnp879 -e "create database schoolbox";
mysql -u root -ptnp879 schoolbox < $cachedir/schoolboxdemo-$demo_data_version.sql;

mysql -u root -ptnp879 schoolbox -e "UPDATE config SET value='$external_db' WHERE name='external_type'";
mysql -u root -ptnp879 schoolbox -e "UPDATE config SET value='$external_db_host' WHERE name='external_db_host'";

echo "$vag Creating Schoolbox data directories";
cd /vagrant;
tar -xzf $cachedir/schoolboxdemo-$demo_data_version.tar.gz;
rm -rf /vagrant/data/cache;
/vagrant/tools/create-data-dirs.sh /vagrant/data;

echo "$vag Creating Schoolbox conf file";
cp -f /vagrant/www/conf/tarball.conf.php /vagrant/www/conf/conf.php;
sed -i 's/xxxxxxxx/vagrantx/g' /vagrant/www/conf/conf.php;
sed -i 's/xxxxxx/tnp879/g' /vagrant/www/conf/conf.php;

echo "$vag Installing vendor submodules with composer";
/vagrant/tools/composer.sh;

echo "$vag Building SQL updates";
cd tools;
./build-sql-update.sh >/dev/null;
cd /vagrant;

echo "$vag Generating data dir sql fixes";
cp -f $cachedir/schoolboxdemo-vagrant-$demo_data_version.sql /tmp/schoolboxdemo-vagrant-$demo_data_version.sql;
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /tmp/schoolboxdemo-vagrant-$demo_data_version.sql;

echo "$vag Applying vagrant SQL fixes";
mysql -u root -ptnp879 schoolbox < /tmp/schoolboxdemo-vagrant-$demo_data_version.sql

echo "$vag Upgrading demo data with latest SQL updates";
mkdir /var/lib/vagrant -p > /dev/null;
/vagrant/tools/update-sql.sh;
rm -f /vagrant/updates/*/scripts_list > /dev/null;
rm -R /var/lib/vagrant > /dev/null;
rm -f /vagrant/www/conf/pre*conf.php;

echo "$vag Generating data dir sql fixes";
cp -f $cachedir/schoolboxdemo-vagrant-$demo_data_version.sql /tmp/schoolboxdemo-vagrant-$demo_data_version.sql;
/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /tmp/schoolboxdemo-vagrant-$demo_data_verison.sql;

echo "$vag Applying vagrant SQL fixes";
mysql -u root -ptnp879 schoolbox < /tmp/schoolboxdemo-vagrant-$demo_data_version.sql

echo "$vag Applying UTF8 schema patch";
/vagrant/tools/schema/collation.sh;

echo "$vag Removing existing apache vhosts";
rm -f /etc/apache2/sites-enabled/10*;
rm -f /etc/apache2/sites-enabled/*schoolbox*;
sed -i 's/vagrant\/data\/vagrant-xhdebug/vagrant\/vagrant-xhdebug/g' /etc/apache2/sites-enabled/*xhdebug*.conf;

echo "$vag Enabling Schoolbox apache vhost";
sed 's/usr\/share\/schoolbox/vagrant/g' /vagrant/tools/apache-vhost-schoolbox > /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/php_flag\ display_startup_errors\ off/php_flag\ display_startup_errors\ on/g' /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/php_flag\ display_errors\ off/php_flag\ display_errors\ on/g' /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/php_flag\ html_errors\ off/php_flag\ html_errors\ on/g' /etc/apache2/sites-available/schoolbox.conf;
sed -i 's/var\/log\/apache2/vagrant/g' /etc/apache2/sites-available/schoolbox.conf;
a2ensite schoolbox.conf
echo 'Listen 8000' > /etc/apache2/sites-available/schoolbox-dav.conf;
sed 's/usr\/share\/schoolbox/vagrant/g' /vagrant/tools/apache-vhost-schoolbox-webdav >> /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/php_flag\ display_startup_errors\ off/php_flag\ display_startup_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/php_flag\ display_errors\ off/php_flag\ display_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/php_flag\ html_errors\ off/php_flag\ html_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/var\/log\/apache2/vagrant/g' /etc/apache2/sites-available/schoolbox-dav.conf;
sed -i 's/*:80/*:8000/g' /etc/apache2/sites-available/schoolbox-dav.conf;
a2ensite schoolbox-dav.conf
puppet apply -e 'package { "javascript-common": ensure => absent }' > /dev/null;

echo "$vag Enabling Schoolbox nginx vhost";
cp -f /vagrant/tools/nginx-vhost-schoolbox-websocket.conf /etc/nginx/conf.d/;
sed -i 's/var\/log\/nginx/vagrant/g' /etc/nginx/conf.d/nginx-vhost-schoolbox-websocket.conf;

echo "$vag Set up video cronjobs"
cp -f /vagrant/tools/cron/schoolboxdev $cachedir/schoolboxdemo-latest-cron
sed -i 's/usr\/share\/schoolboxdev/vagrant/g' $cachedir/schoolboxdemo-latest-cron
sed -i 's/var\/log/vagrant\/data/g' $cachedir/schoolboxdemo-latest-cron
sed -i 's/schoolboxdev/vagrant/g' $cachedir/schoolboxdemo-latest-cron
sed -i 's/www-data/root/g' $cachedir/schoolboxdemo-latest-cron
cp -f $cachedir/schoolboxdemo-latest-cron /etc/cron.d/vagrant

echo "$vag Installing grunt-cli if not already installed";
/vagrant/tools/grunt.sh;

echo "$vag Building CSS and JS";
/vagrant/tools/pkg-build-css-js.sh;

echo "$vag Starting watcher for changes to CSS files";
grunt --no-color watch > /vagrant/data/watch.output.txt &

echo "$vag Enabling modules";
/vagrant/tools/module.sh enable leave;
/vagrant/tools/module.sh enable resource;

echo "$vag Loading all demo data complete";

echo "$vag Run Notifications binder";
cd /vagrant/tools/mq;
./binder.php &

echo "$vag Install schoolbox-scheduled";
cp -f /vagrant/tools/init/upstart-scheduled.conf /etc/init/schoolbox-scheduled.conf;
cp -f /vagrant/tools/init/upstart-scheduled.override /etc/init/schoolbox-scheduled.override;

echo "$vag Install schoolbox-socketd";
cp -f /vagrant/tools/init/upstart-socketd.conf /etc/init/schoolbox-socketd.conf;
cp -f /vagrant/tools/init/upstart-socketd.override /etc/init/schoolbox-socketd.override;

echo "$vag Install schoolbox-digestd";
cp -f /vagrant/tools/init/upstart-digestd.conf /etc/init/schoolbox-digestd.conf;
cp -f /vagrant/tools/init/upstart-digestd.override /etc/init/schoolbox-digestd.override;

echo "$vag Install schoolbox-rerouted";
cp -f /vagrant/tools/init/upstart-rerouted.conf /etc/init/schoolbox-rerouted.conf;
cp -f /vagrant/tools/init/upstart-rerouted.override /etc/init/schoolbox-rerouted.override;

echo "$vag Install schoolbox-belld";
cp -f /vagrant/tools/init/upstart-belld.conf /etc/init/schoolbox-belld.conf;
cp -f /vagrant/tools/init/upstart-belld.override /etc/init/schoolbox-belld.override;

echo "$vag Ensure schoolbox-epl is of the required version";
eplver='1.4.1-4';
if [ "`dpkg -l schoolbox-epl | tail -n1 | awk {'print $3'}`" != "$eplver" ];
then
	apt-get update >/dev/null 2>&1;
	apt-get -y install schoolbox-epl;
fi

echo "$vag Ensure schoolbox-ffmpeg is of the required version";
ffmpegver='2.6.3-1';
if [ "`dpkg -l schoolbox-ffmpeg | tail -n1 | awk {'print $3'}`" != "$ffmpegver" ];
then
	apt-get update >/dev/null 2>&1;
	apt-get -y install schoolbox-ffmpeg;
fi

echo "$vag Installing all required packages";
apt-get -y install imagemagick php5-imagick;

if [ "`apache2 -v | grep Apache | cut -d'/' -f2 | cut -d'.' -f1-2`" == "2.4" ];
then
	echo "$vag Apache is v2.4.x, changing access config to match";
	sed -i 's/Order\ allow,deny//g' /etc/apache2/sites-available/*.conf;
	sed -i 's/Allow\ from\ all/Require\ all\ granted/g' /etc/apache2/sites-available/*.conf;
fi

echo "$vag Ensure all services are running";
service schoolbox-epl restart > /dev/null 2>&1;
#service mysql restart > /dev/null 2>&1;
service apache2 restart > /dev/null 2>&1;
service nginx restart > /dev/null 2>&1;
service schoolbox-scheduled restart > /dev/null 2>/dev/null &
service schoolbox-socketd restart > /dev/null 2>/dev/null &
service schoolbox-digestd restart > /dev/null 2>/dev/null &
service schoolbox-rerouted restart > /dev/null 2>/dev/null &
service schoolbox-belld restart > /dev/null 2>/dev/null &

for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
	echo "$vag Website URL => http://$eth/";
	echo "$vag WebDav URL => http://$eth:8000/";
done
