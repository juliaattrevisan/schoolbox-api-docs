#!/bin/bash
# Can be invoked to either:
# 1. Builds and runs a taskd container from local source, or
# 2. Stops and removes any taskd containers build from local source.

# Global const
readonly ARG_LOCAL="local"                              # If present, build image from local source. If absent, remove locally-built containers.
readonly ARG_ENV_FILE="env-file"                        # If present, start the locally-built image with this env file.
readonly IMAGE_TAG="local"                              # So we can distinguish locally-built image from systemd service.
readonly LOCAL_CONTAINER_NAME="schoolbox_vagrant_taskd" # So we can distinguish locally-built container from systemd service.
readonly MOUNT_DIR_TASKD="/tmp/taskd"

# Global vars
declare build_locally="0"
declare env_file

# Read command line options
function parse_args() {
  opts=$(getopt \
    --longoptions "$ARG_LOCAL,$ARG_ENV_FILE:" \
    --name "$(basename "$0")" \
    --options "" \
    -- "$@"
  )
  eval set --"$opts"
  while [[ $# -gt 0 ]]; do
    case "$1" in
      "--$ARG_LOCAL")
        build_locally="1"
        shift;
        ;;
      "--$ARG_ENV_FILE")
        env_file=$2
        shift 2
        ;;
      "--")
        shift
        ;;
    esac
  done
}

function validate_args(){
  local errors="0"
  # env file provided and does not exist? Error
  if [ -n "$env_file" ] && [ ! -f "$env_file" ]; then
    echo "--$ARG_ENV_FILE provided, but does not exist";
    errors="1"
  fi

  if [[ $build_locally == "1" ]] && [[ ! -f "$MOUNT_DIR_TASKD/Dockerfile" ]]; then
    echo "[SB][$(date +%Y-%m-%d\ %X)] Dockerfile '$MOUNT_DIR_TASKD/Dockerfile' does not exist." >&2
    echo "[SB][$(date +%Y-%m-%d\ %X)] This generally indicates you've just switched the value of 'taskd_build_from_local' in your vagrant file to 'true'." >&2
    echo "[SB][$(date +%Y-%m-%d\ %X)] Please try a \`vagrant reload && vagrant provision\`" >&2
    errors="1"
  fi

  if [[ $errors == "1" ]]; then
    exit 1
  fi
}

function stop_container () {
  # stop existing locally-built container (identified via image tag + container name)
  echo "[SB][$(date +%Y-%m-%d\ %X)] Stopping locally-built task daemon container";
  taskd_container_ids="$(docker ps -f ancestor=alaress/taskd:$IMAGE_TAG -q)"
  if [[ -n $taskd_container_ids ]]; then
    echo -e "$taskd_container_ids" | xargs docker stop
  fi
  taskd_container_ids="$(docker ps -f name="$LOCAL_CONTAINER_NAME" -q)"
  if [[ -n $taskd_container_ids ]]; then
    echo -e "$taskd_container_ids" | xargs docker stop
  fi
}


# build image
function build_image(){
  echo "[SB][$(date +%Y-%m-%d\ %X)] Rebuilding local task daemon image";
  docker build -q --tag alaress/taskd:$IMAGE_TAG --network host "$MOUNT_DIR_TASKD"
}


function start_container () {
  declare extra_args=()

  if [ -n "$env_file" ]; then
    echo "[SB][$(date +%Y-%m-%d\ %X)] Restarting task daemon container with .env configuration file: '${1}'";
    extra_args+=(--env-file="$env_file")
  else
    echo "[SB][$(date +%Y-%m-%d\ %X)] Restarting task daemon container with default configuration";
  fi

  docker run -d --rm \
    --network host \
    --name "$LOCAL_CONTAINER_NAME" \
    "${extra_args[@]}" \
    alaress/taskd:$IMAGE_TAG
}

function main(){
  parse_args "$@"
  validate_args

  stop_container;

  # if managing locally, build image and run container.
  if [[ $build_locally == "1" ]]; then
    build_image
    start_container "$env_file";
  fi
}

main "$@"
