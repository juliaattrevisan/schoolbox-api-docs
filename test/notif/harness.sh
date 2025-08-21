#!/bin/bash

# yo, u root?
if [ "$(id -u)" != "0" ]; then
    echo "Make me a sandwich."
    exit 1
fi

WD_ROOT="/usr/share/schoolbox"
INSTANCE_ID="vagrantx"

# move to application root directory
cd $WD_ROOT;

# slay the demons
tools/notif/allstop

# clear redis of all messaging
redis-cli keys "msg:${INSTANCE_ID}:*" | xargs redis-cli del

# reset the database
tools/vagrant-rebuild-database.sh

# ensure rabbit is running
service rabbitmq-server start

# ensure notifications go everywhere always
mariadb schoolbox < sql/notifications-all-instant.sql

# create a special hell for yourself (we don't need socket or digest presently)
service schoolbox-cloud-scheduled@vagrantx start

# run the test case
php test/notif/harness.php 2> /dev/null
