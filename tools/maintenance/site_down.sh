#!/usr/bin/env bash
# Simple script to place schoolbox into maintenance mode.
# 1. Ensures maintenance file is present
# 2. Reloads nginx config to detect the presence of the file
# IMPORTANT - IF YOU CHANGE THIS FILE, YOU MUST PORT THE SAME LOGICAL CHANGE TO debian/preinst

# Check we're running as root
if [[ "$EUID" -ne 0 ]]
  then echo "Please run as root, aborting"
  exit
fi

echo 'Taking Schoolbox offline...';

# Get this file's directory, and derive the maintenance filename relatively from it
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
maintenanceFileName=`realpath "$DIR/../../.maintenance"`;

# Does maintenance file already exist
echo "Checking for maintenance file '$maintenanceFileName'..."
if [[ -f ${maintenanceFileName} ]]; then
  echo "Maintenance file already present.";
else
  echo "Maintenance file not present, creating...";
  touch ${maintenanceFileName};
  if [[ $? != 0 ]]; then
    echo "Unable to create maintenance file, exiting";
    exit 1;
  fi;
  echo "Maintenance file created at '$maintenanceFileName'";
fi;

# Production servers use 'open_file_cache', need to reload to detect file change
nginx -s reload
