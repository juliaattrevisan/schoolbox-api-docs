#!/bin/bash

# stop existing container (by base image, then by container name)
echo "[SB][`date +%Y-%m-%d\ %X`] Stopping task daemon container";
taskd_container_ids="$(docker ps -f ancestor=alaress/taskd:latest -q)"
if [[ -n $taskd_container_ids ]]; then
  echo -e "$taskd_container_ids" | xargs docker stop
fi
taskd_container_ids="$(docker ps -f name=schoolbox_vagrant_taskd -q)"
if [[ -n $taskd_container_ids ]]; then
  echo -e "$taskd_container_ids" | xargs docker stop
fi

# Should the task daemon container be built here or not?

# build image: /tmp/taskd is assumed
echo "[SB][`date +%Y-%m-%d\ %X`] Rebuilding task daemon image";
docker build -q -t alaress/taskd:latest --network host /tmp/taskd

# start container
echo "[SB][`date +%Y-%m-%d\ %X`] Restarting task daemon container";
docker run -d --rm --network host --name schoolbox_vagrant_taskd alaress/taskd:latest
