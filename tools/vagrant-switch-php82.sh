#!/bin/bash

echo "[SB][$(date +%Y-%m-%d\ %X)] Switching CLI PHP to 8.2"
sudo update-alternatives --set php /usr/bin/php8.2

echo "[SB][$(date +%Y-%m-%d\ %X)] Updating Composer to current Puppet version"
sudo curl -sS https://getcomposer.org/installer | sudo php --  --version=2.5.8 --install-dir=/usr/local/bin --filename=composer

echo "[SB][`date +%Y-%m-%d\ %X`] Stopping php8.4-fpm, starting php8.2-fpm and Puppet";
sudo service php8.4-fpm stop
sudo service php8.2-fpm start
sudo service puppet start
