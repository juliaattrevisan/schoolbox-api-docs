#!/bin/sh
filepath=`readlink -f $0`;
fullpath=${filepath%/*};

if [ -w /var/log ];
then
  logfile=/var/log/insert-missing-plagscan-`date +%F`.log;
else
  logfile=insert-missing-plagscan-`date +%F`.log;
fi;

echo "Start inserting missing plagscan documents (logged in $logfile)";
php $fullpath/../../tools/plagscan/insert-missing-plagscan-document.php -p > $logfile 2>&1 &
