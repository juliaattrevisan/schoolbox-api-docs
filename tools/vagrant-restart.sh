#!/bin/bash

# Global constants and functions
source /usr/share/schoolbox/tools/vagrant-consts.sh # Cannot derive this, because it is copied by vagrant to /tmp in guest

primary_php_majver=$1
if [[ -z $primary_php_majver ]]; then
  echo "You must declare primary/active PHP major version" >&2
  exit 1
fi
taskd_build_from_local="${2}";

# Override vagrant's network configuration with this host's network config
/etc/init.d/procps restart

# wait for /usr/share/schoolbox to mount correctly
for i in {1..60};
do
    if [ -f "$SITE_ROOT/tools/notif/syscheck.php" ];
    then
        break;
    fi
    sleep 1;
done
if [ -f "$SITE_ROOT/tools/notif/syscheck.php" ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Restarting daemons in background";
    sleep 5 && service schoolbox-cloud-socketd restart &
    sleep 5 && service schoolbox-cloud-digestd@vagrantx restart &
    sleep 5 && service schoolbox-cloud-scheduled@vagrantx restart &
else
    echo "Unable to locate: $SITE_ROOT/tools/notif/syscheck.php";
fi


# Restart dependent containers:
# * Etherpad
# * Gotenberg
# * Taskd (whether from Dockerhub or local)
echo "[SB][`date +%Y-%m-%d\ %X`] Restarting Etherpad service";
service schoolbox-epl restart;

echo "[SB][`date +%Y-%m-%d\ %X`] Stopping Gotenberg service";
gotenberg_container_ids="$(docker ps -f name=schoolbox_vagrant_gotenberg -q)"
if [[ -n $gotenberg_container_ids ]]; then
  echo -e "$gotenberg_container_ids" | xargs docker stop
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Installing and starting Gotenberg service";
docker run -d --rm --network host --name schoolbox_vagrant_gotenberg gotenberg/gotenberg:7;

# Rebuild & restart task daemon
# if `taskd_build_from_local` then build from the mounted taskd dir
declare taskd_args=()
if [ "$taskd_build_from_local" == 1 ]; then
  taskd_args+=(--local)
fi;
if ! "$SITE_ROOT"/tools/taskd/rebuild.sh "${taskd_args[@]}"; then
  exit 1
fi

# Need to read the actual run-time value, rather than puppet-configured value, as it's possible to
# `vagrant up` without a `vagrant provision` so the puppet value may not have yet been applied
# get port for newer rabbit versions
rabbitmq_management_port=$(rabbitmqctl status | grep -E "HTTP API" | awk -F',| ' '{print $5}')

# This will fail to start when VM booted because /usr/share/schoolbox may not yet be mounted
eval "service php${primary_php_majver}-fpm restart"

for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
  echo "[SB][`date +%Y-%m-%d\ %X`] Website URL             => https://$eth/";
done

echo "[SB][`date +%Y-%m-%d\ %X`] Website URL             => https://vagrant.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL              => https://vagrant-dav.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] RabbitMQ Management URL => http://vagrant.dev.schoolbox.cloud:$rabbitmq_management_port/";
