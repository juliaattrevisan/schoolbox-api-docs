#!/bin/bash

initialdir=$(pwd);                      # eg "/tmp/build/6963e648"

export INSTANCE_ID=smoketest

## RABBITMQ

service rabbitmq-server start
rabbitmq-plugins enable rabbitmq_management

cp /usr/share/schoolbox/tools/ci/rabbitmq.config /etc/rabbitmq/rabbitmq.config

sudo mkdir -p /usr/lib/rabbitmq/plugins/
sudo wget https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/v3.12.0/rabbitmq_delayed_message_exchange-3.12.0.ez -P /usr/lib/rabbitmq/plugins/
rabbitmq-plugins enable rabbitmq_stomp
rabbitmq-plugins enable rabbitmq_delayed_message_exchange

chown -R rabbitmq:rabbitmq /var/lib/rabbitmq/

rabbitmqctl add_user schoolbox schoolbox
rabbitmqctl add_vhost schoolbox
rabbitmqctl set_permissions -p schoolbox schoolbox ".*" ".*" ".*"

mariadb schoolbox -e "UPDATE config SET value = 'schoolbox' WHERE name IN ('amqp_user', 'amqp_pass', 'amqp_vhost', 'site_hostname')"

service rabbitmq-server restart

## INSTANCE DAEMONS

logdir=/var/log/schoolbox/smoketest

/usr/share/schoolbox/tools/notif/syscheck.php -vvv

echo "starting socketd"
sudo -u www-data bash -c 'INSTANCE_ID=smoketest /usr/share/schoolbox/tools/socket/socketd.php --porcelain -vvv' > $logdir/socketd.log 2>&1 &
# kill -HUP $(pgrep -f socketd.php)

echo "starting scheduled"
sudo -u www-data bash -c 'INSTANCE_ID=smoketest /usr/share/schoolbox/tools/mq/schedule-safe-restart.php -vvv --perform --no-delay'
sudo -u www-data bash -c 'INSTANCE_ID=smoketest /usr/share/schoolbox/tools/mq/scheduled.php --porcelain -vvv --pid' > $logdir/scheduled.log 2>&1 &
# kill -HUP $(pgrep -f scheduled.php)

echo "starting digestd"
sudo -u www-data bash -c 'INSTANCE_ID=smoketest /usr/share/schoolbox/tools/digest/digestd.php --porcelain -vvv --pid' > $logdir/digestd.log 2>&1 &
# kill -HUP $(pgrep -f digestd.php)

echo "starting mobiled"
sudo -u www-data bash -c 'INSTANCE_ID=smoketest /usr/share/schoolbox/tools/mobile/mobiled.php --porcelain -vvv --pid' > $logdir/mobiled.log 2>&1 &
# kill -HUP $(pgrep -f mobiled.php)

echo "refreshing binding cache"
sudo -u www-data bash -c 'INSTANCE_ID=smoketest /usr/share/schoolbox/tools/notif/route/refresh-binding-cache.php'

## ACCEPTANCE TESTS

cd /usr/share/schoolbox
echo "cd into vagrant"

## Extract the NPM build
tar xzf "$initialdir"/npm-artifact/*-npm.tar.gz;

# Re-run NPM publish to ensure the latest versions of files are used,
# as the above build is from a different branch if this is run on a PR
tools/pkg-build-css-js.sh --production

## Install playwright for the test
npx playwright install
echo "playwright installed"

## Finish where we started.
cd "$initialdir"
