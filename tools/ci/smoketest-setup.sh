groupadd nginx
useradd -g nginx nginx
mkdir /var/lib/schoolbox
apt update
apt install git gzip -y
apt install php7.1-fpm php7.1-pdo php7.1-mysql nginx -y
apt install redis-server mysql-client grunt npm -y
npm install grunt
apt install mariadb-server -y
# latest build should have been pulled down
# configfiles.tar.gz should exist for us to use
# sales dataset should exist
# vagrant_config.sql should exist in config files
# start and initialise the database with a user, password, user ect.
service mysql start

mysql -e "create database schoolbox;"
mysql -e "create user 'schoolboxvagrant'@'%' IDENTIFIED BY 'abc123';"
mysql -e "grant all privileges on schoolbox.* to 'schoolboxvagrant'@'%';"

# initialise the data itself
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql -pPassw0rd!
mysql schoolbox < /tmp/vagrant_config.sql

cd /
mkdir vagrant
mkdir mnt/data
mkdir mnt/data/php_tmp
mkdir vagrant/data
chmod 777 vagrant/data
cp -rf schoolbox/* vagrant
rm -rf schoolbox
cd /tmp
cp -rf develop-build.tar.gz/* /vagrant
cp configfiles.tar.gz/configfiles/dhparams.pem /etc/dhparams.pem
cp -rf configfiles.tar.gz/configfiles/nginx/* /etc/nginx
cp -rf configfiles.tar.gz/configfiles/php/7.1/fpm/* /etc/php/7.1/fpm
cp configfiles.tar.gz/configfiles/redis/redis.conf /etc/redis/redis.conf
cp configfiles.tar.gz/configfiles/www-conf/conf.php /vagrant/www/conf/conf.php
cp -rf sales.tar.gz/data/* /vagrant/data
cd /vagrant
composer install
npm install
grunt build
service php7.1-fpm start
service nginx start
service redis-server start
service mysql start

