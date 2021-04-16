#!/bin/bash
# wait for /vagrant to mount correctly
for i in {1..60};
do
    if [ -f "/vagrant/tools/notif/syscheck.php" ];
    then
        break;
    fi
    sleep 1;
done
if [ -f "/vagrant/tools/notif/syscheck.php" ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Restarting daemons in background";
    sleep 5 && service schoolbox-cloud-socketd@vagrantx restart &
    sleep 5 && service schoolbox-cloud-routed@vagrantx restart &
    sleep 5 && service schoolbox-cloud-digestd@vagrantx restart &
    sleep 5 && service schoolbox-cloud-scheduled@vagrantx restart &
else
    echo "Unable to locate: /vagrant/tools/notif/syscheck.php";
fi

# Need to read the actual run-time value, rather than puppet-configured value, as it's possible to
# `vagrant up` without a `vagrant provision` so the puppet value may not have yet been applied
rabbitmq_management_port=$(rabbitmqctl status | grep -Eo "http,[0-9]+" | awk -F',' '{ print $2 }')

# This will fail to start when VM booted because /vagrant may not yet be mounted
service php7.4-fpm restart

for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
  echo "[SB][`date +%Y-%m-%d\ %X`] Website URL             => https://$eth/";
  echo "[SB][`date +%Y-%m-%d\ %X`] RabbitMQ Management URL => http://$eth:$rabbitmq_management_port/";
done

echo "[SB][`date +%Y-%m-%d\ %X`] Website URL             => https://vagrant.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL              => https://vagrant-dav.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] RabbitMQ Management URL => http://vagrant.dev.schoolbox.cloud:$rabbitmq_management_port/";
