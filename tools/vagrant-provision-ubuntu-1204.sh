#!/bin/bash
vag="[schoolbox]";
bridgeip=`ifconfig | grep "eth1" -C2 | grep "inet addr" | awk '{ print $2; }' | cut -d':' -f2`;
echo "$vag Bridged Network Address => $bridgeip";
if [ ! -d /vagrant/data ];
then
	cd /home/vagrant;
	echo "$vag Downloading Schoolbox demo data";
	rm -vf *latest*;
	wget -q http://hydra.alrs/boxes/schoolboxdemo-latest.tar.gz
	wget -q http://hydra.alrs/boxes/schoolboxdemo-latest.sql
	wget -q http://hydra.alrs/boxes/schoolboxdemo-latest.vhost
	echo "$vag Removing existing apache vhosts";
	rm -f /etc/apache2/sites-enabled/*;
	echo "$vag Enabling Schoolbox apache vhost";
	cp /home/vagrant/schoolboxdemo-latest.vhost /etc/apache2/sites-available/schoolbox.vhost
	a2ensite schoolbox.vhost
	service apache2 restart
	echo "$vag Restarting MySQL";
	service mysql restart;
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
	echo "$vag Loading all demo data complete";
fi
