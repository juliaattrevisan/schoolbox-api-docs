#!/bin/bash
function sync_puppet() {
    puppet_dir=$1
    cmd=$2
    current_dir=$(pwd)
    if ! (cd "$puppet_dir" && eval "$cmd" && cd "$current_dir"); then
      echo "Error: puppet failed to sync" >&2
      exit 1
    fi
}

puppet_sync_type=$1
puppet_dir=$2
GIT_PULL_CMD="git pull --ff-only origin stable"
GITSYNC="gitsync stable"
cmd=""
case $puppet_sync_type in
  pull)
    echo "Syncing puppet with git pull"
    cmd=$GIT_PULL_CMD
    sync_puppet "$puppet_dir" "$cmd"
    ;;
  gitsync)
    echo "Syncing puppet with gitsync"
    cmd=$GITSYNC
    sync_puppet "$puppet_dir" "$cmd"
    ;;
  *)
    echo "Error: puppet_sync_type must be either pull, gitsync, you entered $puppet_sync_type" >&2
    exit 1;
    ;;
esac
