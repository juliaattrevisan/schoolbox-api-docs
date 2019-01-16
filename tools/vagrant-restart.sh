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
    sleep 5 && service schoolbox-socketd restart &
    sleep 5 && service schoolbox-routed restart &
    sleep 5 && service schoolbox-digestd restart &
    sleep 5 && service schoolbox-scheduled restart &
else
    echo "Unable to locate: /vagrant/tools/notif/syscheck.php";
fi

for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
    echo "[SB][`date +%Y-%m-%d\ %X`] Website URL => http://$eth/";
    echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL => http://$eth:8881/";
done

echo "[SB][`date +%Y-%m-%d\ %X`] Website URL => https://vagrant.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL => https://vagrant.dev.schoolbox.cloud:8882/";
echo "[SB][`date +%Y-%m-%d\ %X`] Styleguide URL => http://vagrant.dev.schoolbox.cloud:8883/";
