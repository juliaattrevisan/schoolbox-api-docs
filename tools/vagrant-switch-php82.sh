#!/bin/bash

echo "Switching CLI PHP to 8.2"
sudo update-alternatives --set php /usr/bin/php8.2

echo "Updating Composer to current Puppet version"
sudo curl -sS https://getcomposer.org/installer | sudo php --  --version=2.5.8 --install-dir=/usr/local/bin --filename=composer

echo "Stopping php8.4-fpm, starting php8.2-fpm and Puppet";
sudo service php8.4-fpm stop
sudo service php8.2-fpm start
sudo service puppet start

echo "Restarting daemons";
sudo tools/notif/allrestart
sudo systemctl restart schoolbox-cloud-socketd
