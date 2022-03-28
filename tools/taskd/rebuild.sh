#!/bin/bash

# Go to the given folder
cd $1

# stop existing machine
echo "[SB][`date +%Y-%m-%d\ %X`] Stopping task daemon service";
taskd_container_ids="$(docker ps -f ancestor=alaress/taskd:latest -q)"
if [[ -n $taskd_container_ids ]]; then
  echo -e "$taskd_container_ids" | xargs docker stop
fi

# build image
docker build -t alaress/taskd:latest --network host .

# start container
docker run -d --rm --network host --name taskd alaress/taskd:latest
