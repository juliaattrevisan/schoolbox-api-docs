#!/bin/bash
# PHP Switch Script
# Description:
#       This script will switch the version of PHP running on the current host between 7.1 and 5.5
#
# @author      James Leckie <james.leckie@alaress.com.au>
# @copyright   2017 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#

phpversion=`php -v | head -n1 | awk '{print $2}' | cut -c1-3`;
echo "Checking for current PHP version: $phpversion";

if [ "$phpversion" == "7.1" ];
then
    a2dismod php7.1;
    a2enmod php5;
    update-alternatives --set php /usr/bin/php5
    service apache2 restart;
    service schoolbox-scheduled restart;
    service schoolbox-routed  restart;
    service schoolbox-socketd   restart;
    service schoolbox-digestd   restart;
    echo "Switched PHP version to: 5.5";
elif [ "$phpversion" == "5.5" ];
then
    a2dismod php5;
    a2enmod php7.1;
    update-alternatives --set php /usr/bin/php7.1
    service apache2 restart;
    service schoolbox-scheduled restart;
    service schoolbox-routed  restart;
    service schoolbox-socketd   restart;
    service schoolbox-digestd   restart;
    echo "Switched PHP version to: 7.1";
fi