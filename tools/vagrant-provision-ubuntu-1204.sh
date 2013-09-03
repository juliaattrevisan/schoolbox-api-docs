#!/bin/bash
vag="[schoolbox]";
if [ ! -d /vagrant/data ];
then
	cd /home/vagrant;
	echo "$vag Downloading Schoolbox demo data";
	rm -vf *latest*;
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-latest.tar.gz
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-latest.sql
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-vagrant-latest.sql
	echo "$vag Restarting MySQL";
	service mysql restart > /dev/null;
	echo "$vag Stopping Apache";
	service apache2 stop > /dev/null;
	echo "$vag Removing current Schoolbox MySQL database";
	mysql -u root -ptnp879 -e "drop database if exists schoolbox";
	echo "$vag Loading Schoolbox MySQL Demo data";
	mysql -u root -ptnp879 -e "create database schoolbox";
	mysql -u root -ptnp879 schoolbox < /home/vagrant/schoolboxdemo-latest.sql
	echo "$vag Creating Schoolbox data directories";
	cd /vagrant;
	tar -xzf /home/vagrant/schoolboxdemo-latest.tar.gz;
	rm -rf /vagrant/data/cache;
	/vagrant/tools/create-data-dirs.sh /vagrant/data;
	echo "$vag Creating Schoolbox conf file";
	cp -f /vagrant/www/conf/tarball.conf.php /vagrant/www/conf/conf.php;
	sed -i 's/xxxxxx/tnp879/g' /vagrant/www/conf/conf.php;
	echo "$vag Building css files via less";
	/vagrant/tools/build-less-css.sh;
	echo "$vag Installing vendor submodules with composer";
	/vagrant/tools/composer.sh;
	echo "$vag Building SQL updates";
	cd tools;
	./build-sql-update.sh;
	cd /vagrant;
	echo "$vag Upgrading demo data with latest SQL updates";
	mkdir /var/lib/vagrant -p > /dev/null;
	/vagrant/tools/update-sql.sh;
	rm -f /vagrant/updates/*/scripts_list > /dev/null;
	rm -vR /var/lib/vagrant > /dev/null;
	echo "$vag Generating data dir sql fixes";
	/vagrant/tools/generate-dir-sql.sh /vagrant/data /vagrant >> /home/vagrant/schoolboxdemo-vagrant-latest.sql;
	echo "$vag Applying vagrant SQL fixes";
	mysql -u root -ptnp879 schoolbox < /home/vagrant/schoolboxdemo-vagrant-latest.sql
	echo "$vag Switching on debug mode for development";
	mysql -u root -ptnp879 -e "UPDATE config SET value='1' WHERE name='debug_mode';" schoolbox
	echo "$vag Removing existing apache vhosts";
	rm -f /etc/apache2/sites-enabled/10*;
	echo "$vag Enabling Schoolbox apache vhost";
	sed -i 's/vagrant\/vagrant-xhdebug/vagrant\/data\/vagrant-xhdebug/g' /etc/apache2/sites-enabled/*xhdebug*
	sed 's/usr\/share\/schoolbox/vagrant/g' /vagrant/tools/apache-vhost-schoolbox > /etc/apache2/sites-available/schoolbox.vhost;
	sed -i 's/php_flag\ display_startup_errors\ off/php_flag\ display_startup_errors\ on/g' /etc/apache2/sites-available/schoolbox.vhost;
	sed -i 's/php_flag\ display_errors\ off/php_flag\ display_errors\ on/g' /etc/apache2/sites-available/schoolbox.vhost;
	sed -i 's/php_flag\ html_errors\ off/php_flag\ html_errors\ on/g' /etc/apache2/sites-available/schoolbox.vhost;
	sed -i 's/var\/log\/apache2/vagrant\/data/g' /etc/apache2/sites-available/schoolbox.vhost;
	a2ensite schoolbox.vhost
	echo 'Listen 8000' > /etc/apache2/sites-available/schoolbox-dav.vhost;
	sed 's/usr\/share\/schoolbox/vagrant/g' /vagrant/tools/apache-vhost-schoolbox-webdav >> /etc/apache2/sites-available/schoolbox-dav.vhost;
	sed -i 's/php_flag\ display_startup_errors\ off/php_flag\ display_startup_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.vhost;
	sed -i 's/php_flag\ display_errors\ off/php_flag\ display_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.vhost;
	sed -i 's/php_flag\ html_errors\ off/php_flag\ html_errors\ on/g' /etc/apache2/sites-available/schoolbox-dav.vhost;
	sed -i 's/var\/log\/apache2/vagrant\/data/g' /etc/apache2/sites-available/schoolbox-dav.vhost;
	sed -i 's/*:80/*:8000/g' /etc/apache2/sites-available/schoolbox-dav.vhost;
	a2ensite schoolbox-dav.vhost
	puppet apply -e 'package { "javascript-common": ensure => absent }' > /dev/null;
	echo "$vag Set up video cronjobs"
	cp -f /vagrant/tools/cron/schoolboxdev /home/vagrant/schoolboxdemo-latest-cron
	sed -i 's/usr\/share\/schoolboxdev/vagrant/g' /home/vagrant/schoolboxdemo-latest-cron
	sed -i 's/var\/log/vagrant\/data/g' /home/vagrant/schoolboxdemo-latest-cron
	sed -i 's/schoolboxdev/vagrant/g' /home/vagrant/schoolboxdemo-latest-cron
	sed -i 's/www-data/root/g' /home/vagrant/schoolboxdemo-latest-cron
	cp -f /home/vagrant/schoolboxdemo-latest-cron /etc/cron.d/vagrant
	echo "$vag Installing Redis";
	keycheck=`apt-key list | grep BC45BCB5 | wc -l`;
	if [ $keycheck -eq 0 ];
	then
		apt-key adv --keyserver keyserver.ubuntu.com --recv-keys BC45BCB5
	fi

	keycheck=`apt-key list | grep BC45BCB5 | wc -l`;
	if [ $keycheck -eq 0 ];
	then
		echo "$vag Error installing php-redis keys";
		exit 1;
	else
		if [ ! -f /etc/apt/sources.list.d/php-redis.list ];
		then
			echo 'deb http://ppa.launchpad.net/ufirst/php/ubuntu precise main' >> /etc/apt/sources.list.d/php-redis.list;
			echo 'deb-src http://ppa.launchpad.net/ufirst/php/ubuntu precise main' >> /etc/apt/sources.list.d/php-redis.list
			apt-get -y update > /dev/null 2>&1;
		fi
	fi

	apt-get -y install php5-redis redis-server
	apt-get -y install libpspell-dev php5-pspell aspell-en php5 php5-dev php-pear > /dev/null 2>&1;
	echo "$vag Loading all demo data complete";
fi
echo "$vag Ensure all services are running";
service mysql restart > /dev/null;
service apache2 restart > /dev/null;
for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
	echo "$vag Website URL => http://$eth/";
	echo "$vag WebDav URL => http://$eth:8000/";
done
