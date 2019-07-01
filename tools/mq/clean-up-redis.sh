#!/bin/sh
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

# the actual log file path is defined in clean-up-redis.php
if [ -w /var/log ];
then
  logfile=/var/log/clean-up-redis-`date +%F`.log;
else
  logfile=clean-up-redis-`date +%F`.log;
fi;

echo "Starting redis clean up process (logged in $logfile)";
redisfile=/var/lib/redis/dump.rdb;
if [ -f $redisfile ];
then
  ls -lh $redisfile;
fi;
php $fullpath/../../tools/mq/clean-up-redis.php &
