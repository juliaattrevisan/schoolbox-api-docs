#!/bin/bash

primary_php_majver=$1
if [[ -z $primary_php_majver ]]; then
  echo "You must declare primary/active PHP major version" >&2
  exit 1
fi
taskd_build_from_local="${2}";
readonly SITE_ROOT=/usr/share/schoolbox # Cannot derive this, because it is copied by vagrant to /tmp in guest

# Override vagrant's network configuration with this host's network config
/etc/init.d/procps restart

# wait for /usr/share/schoolbox to mount correctly
for i in {1..60};
do
    if [ -f "/usr/share/schoolbox/tools/notif/syscheck.php" ];
    then
        break;
    fi
    sleep 1;
done
if [ -f "/usr/share/schoolbox/tools/notif/syscheck.php" ];
then
    echo "[SB][`date +%Y-%m-%d\ %X`] Restarting daemons in background";
    sleep 5 && service schoolbox-cloud-socketd@vagrantx restart &
    sleep 5 && service schoolbox-cloud-routed@vagrantx restart &
    sleep 5 && service schoolbox-cloud-digestd@vagrantx restart &
    sleep 5 && service schoolbox-cloud-scheduled@vagrantx restart &
else
    echo "Unable to locate: /usr/share/schoolbox/tools/notif/syscheck.php";
fi


# Restart dependent containers:
# * Etherpad
# * Gotenberg
# * Taskd (whether from Dockerhub or local)
echo "[SB][`date +%Y-%m-%d\ %X`] Restarting Etherpad service";
service schoolbox-epl restart;

echo "[SB][`date +%Y-%m-%d\ %X`] Stopping Gotenberg service";
gotenberg_container_ids="$(docker ps -f ancestor=thecodingmachine/gotenberg:6 -q)"
if [[ -n $gotenberg_container_ids ]]; then
  echo -e "$gotenberg_container_ids" | xargs docker stop
fi

echo "[SB][`date +%Y-%m-%d\ %X`] Installing and starting Gotenberg service";
docker run -d --rm --network host --name schoolbox_vagrant_gotenberg thecodingmachine/gotenberg:6;


# Start and rebuild task daemon
# if `taskd_build_from_local` then build from the mounted taskd dir
if [ $taskd_build_from_local -gt 0 ]; then
  "$SITE_ROOT"/tools/taskd/rebuild.sh
else
  accessToken=$(read_var DOCKERHUB_ACCESS_TOKEN)
  if [[ -z $accessToken ]]; then
    echo "[SB][`date +%Y-%m-%d\ %X`] Cannot start task daemon: not building from local dir, and unable to derive Dockerhub access token from .env environment file";
  else
    # Docker login needs the `pass` package to actually accept a password,
    # otherwise this error gets raised:
    # "Cannot autolaunch D-Bus without X11 $DISPLAY"
    # TODO move this to base box
    apt install -y pass
    "$SITE_ROOT"/tools/taskd/rebuild.sh --pull-from-dockerhub --dockerhub-access-token=$accessToken
  fi
fi;


# Need to read the actual run-time value, rather than puppet-configured value, as it's possible to
# `vagrant up` without a `vagrant provision` so the puppet value may not have yet been applied
rabbitmq_management_port=$(rabbitmqctl status | grep -Eo "http,[0-9]+" | awk -F',' '{ print $2 }')

# This will fail to start when VM booted because /usr/share/schoolbox may not yet be mounted
eval "service php${primary_php_majver}-fpm restart"

for eth in `ip addr | grep inet | grep -v inet6 | grep global | grep -v eth0 | awk {'print $2'} | cut -d/ -f1`;
do
  echo "[SB][`date +%Y-%m-%d\ %X`] Website URL             => https://$eth/";
  echo "[SB][`date +%Y-%m-%d\ %X`] RabbitMQ Management URL => http://$eth:$rabbitmq_management_port/";
done

echo "[SB][`date +%Y-%m-%d\ %X`] Website URL             => https://vagrant.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] WebDav URL              => https://vagrant-dav.dev.schoolbox.cloud";
echo "[SB][`date +%Y-%m-%d\ %X`] RabbitMQ Management URL => http://vagrant.dev.schoolbox.cloud:$rabbitmq_management_port/";
