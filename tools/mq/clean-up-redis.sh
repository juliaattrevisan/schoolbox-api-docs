#!/bin/sh
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

logfile=clean-up-redis-`date +%F`.log;

echo "Starting redis clean up process (logged in $logfile)";
sudo ls -sh /var/lib/redis/dump.rdb
php $fullpath/../../tools/mq/clean-up-redis.php &
