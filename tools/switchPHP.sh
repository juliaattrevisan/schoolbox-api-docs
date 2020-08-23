#!/bin/bash
# PHP Switch Script
# Description:
#       This script will switch the version of PHP running on the current host between 7.1 and 7.4
#
# @author      James Leckie <james.leckie@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

phpversion=`php -v | head -n1 | awk '{print $2}' | cut -c1-3`;
echo "Checking for current PHP version: $phpversion";

if [ "$phpversion" == "7.1" ];
then
    update-alternatives --set php /usr/bin/php7.4
    service schoolbox-scheduled restart;
    service schoolbox-routed  restart;
    service schoolbox-socketd   restart;
    service schoolbox-digestd   restart;
    echo "Switched PHP version to: 7.4";
elif [ "$phpversion" == "7.4" ];
then
    update-alternatives --set php /usr/bin/php7.1
    service schoolbox-scheduled restart;
    service schoolbox-routed  restart;
    service schoolbox-socketd   restart;
    service schoolbox-digestd   restart;
    echo "Switched PHP version to: 7.1";
fi
