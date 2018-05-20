#!/bin/bash
# wait for /vagrant to mount correctly
for i in {1..60};
do
    if [ -f /vagrant/tools/notif/syscheck.php ];
    then
        break;
    fi
    sleep 1;
done
sleep 0.5;
service schoolbox-socketd restart;
service schoolbox-routed restart;
service schoolbox-digestd restart;
service schoolbox-scheduled restart;

for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
    echo "[SB][`date +%Y-%m-%d\ %X`] Website URL => http://$eth/";
    echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL => http://$eth:8881/";
done

echo "[SB][`date +%Y-%m-%d\ %X`] Website URL => https://vagrant.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL => https://vagrant.dev.schoolbox.cloud:8882/";
