#!/bin/sh

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"

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

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"