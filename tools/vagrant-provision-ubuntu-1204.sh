#!/bin/bash
vag="[schoolbox]";
bridgeip=`ifconfig | grep "eth1" -C2 | grep "inet addr" | awk '{ print $2; }' | cut -d':' -f2`;
if [ ! -d /vagrant/data ];
then
	cd /home/vagrant;
	echo "$vag Downloading Schoolbox demo data";
	rm -vf *latest*;
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-latest.tar.gz
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-latest.sql
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-latest.vhost
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-latest-dav.vhost
	wget -q http://vagrant.alaress-dev.com.au/schoolboxdemo-vagrant-latest.sql
	echo "$vag Restarting MySQL";
	service mysql restart;
	echo "$vag Stopping Apache";
	service apache2 stop;
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
        echo "$vag Removing existing apache vhosts";
        rm -f /etc/apache2/sites-enabled/*;
        echo "$vag Enabling Schoolbox apache vhost";
        cp /home/vagrant/schoolboxdemo-latest.vhost /etc/apache2/sites-available/schoolbox.vhost
        a2ensite schoolbox.vhost
	cp /home/vagrant/schoolboxdemo-latest-dav.vhost /etc/apache2/sites-available/schoolbox-dav.vhost
	a2ensite schoolbox-dav.vhost
	puppet apply -e 'package { "javascript-common": ensure => absent }' > /dev/null;
        service apache2 restart
	echo "$vag Loading all demo data complete";
fi
echo "$vag Ensure all services are running";
service mysql restart;
service apache2 restart;
echo "$vag Bridged Network Address => $bridgeip";
echo "$vag WebDav URL => http://$bridgeip:8000/";
echo "$vag Website URL => http://$bridgeip/";
