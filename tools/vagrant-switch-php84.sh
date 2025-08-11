#!/bin/bash

echo "Installing PHP 8.4 and dependencies"
sudo apt install -y \
    php8.4-bcmath \
    php8.4-cli \
    php8.4-common \
    php8.4-curl \
    php8.4-excimer \
    php8.4-fpm \
    php8.4-gd \
    php8.4-igbinary \
    php8.4-imagick \
    php8.4-imap \
    php8.4-intl \
    php8.4-ldap \
    php8.4-mbstring \
    php8.4-msgpack \
    php8.4-mysql \
    php8.4-opcache \
    php8.4-phpdbg \
    php8.4-readline \
    php8.4-redis \
    php8.4-xdebug \
    php8.4-xml \
    php8.4-zip \
    php8.4 \
    php8.4-dev

echo "Switching CLI PHP to 8.4"
sudo update-alternatives --set php /usr/bin/php8.4

echo "Installing ev extension for 8.4"
yes | sudo pecl install ev

# TODO install SQLSRV PDO driver: requires PECL + headers

# TODO update Puppet to install newer Composer, instead of updating here
echo "Updating Composer to 8.4-compatible version"
sudo curl -sS https://getcomposer.org/installer | sudo php -- --2.8 --install-dir=/usr/local/bin --filename=composer

echo "Copying 8.2 configuration to 8.4";
sudo cp /etc/php/8.2/mods-available/* /etc/php/8.4/mods-available/
sudo cp /etc/php/8.2/fpm/pool.d/* /etc/php/8.4/fpm/pool.d/

# TODO replace session.serialize_handler

echo "Stopping Puppet and php8.2-fpm, starting php8.4-fpm";
sudo service puppet stop
sudo service php8.2-fpm stop
sudo service php8.4-fpm start

echo "Restarting daemons";
sudo tools/notif/allrestart
sudo systemctl restart schoolbox-cloud-socketd
