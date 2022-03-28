#!/bin/bash


function stop_container() {
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
}


function start_container() {
  echo "[SB][`date +%Y-%m-%d\ %X`] Restarting task daemon container";
  docker run -d --rm --network host --name schoolbox_vagrant_taskd alaress/taskd:latest
}


# Read command line options
pull_from_dockerhub=0;
dockerhub_access_token='';
opts=$(getopt \
    --longoptions "pull-from-dockerhub,dockerhub-access-token:" \
    --name "$(basename "$0")" \
    --options "" \
    -- "$@"
)
eval set --$opts
while [[ $# -gt 0 ]]; do
  case "$1" in
    "--pull-from-dockerhub")
      pull_from_dockerhub=1
      shift;
      ;;
    "--dockerhub-access-token")
      dockerhub_access_token=$2
      shift 2
      ;;
    "--")
      shift
      ;;
  esac
done


# Stop container
# if pulling from dockerhub: pull the image, otherwise build it based on /tmp/taskd
# Start container
if [[ $pull_from_dockerhub -gt 0 ]]; then
  if [[ -z $dockerhub_access_token ]]; then
    echo "--dockerhub-access-token must be provided if --pull-from-dockerhub is set";
    exit 1;
  fi
  stop_container;

  # Pull image
  echo "[SB][`date +%Y-%m-%d\ %X`] Pulling task daemon image";
  echo $dockerhub_access_token | docker login -u alaress --password-stdin;
  docker pull -q alaress/taskd:latest;

  start_container;
else
  stop_container;

  # build image: /tmp/taskd is assumed
  echo "[SB][`date +%Y-%m-%d\ %X`] Rebuilding task daemon image";
  docker build -q -t alaress/taskd:latest --network host /tmp/taskd

  start_container;
fi
