#!/bin/bash
function sync_repo() {
    dir=$1
    cmd=$2
    current_dir=$(pwd)
    if ! (cd "$dir" && eval "$cmd" && cd "$current_dir"); then
      echo "Error: repo failed to sync" >&2
      exit 1
    fi
}

sync_type=$1
dir=$2
branch=$3
GIT_PULL_CMD="git pull --ff-only origin ${branch}"
GITSYNC="gitsync ${branch}"
cmd=""
case $sync_type in
  pull)
    echo "Syncing repo with git pull"
    cmd=$GIT_PULL_CMD
    sync_repo "$dir" "$cmd"
    ;;
  gitsync)
    echo "Syncing repo with gitsync"
    cmd=$GITSYNC
    sync_repo "$dir" "$cmd"
    ;;
  *)
    echo "Error: sync_type must be either pull, gitsync, you entered $sync_type" >&2
    exit 1;
    ;;
esac
