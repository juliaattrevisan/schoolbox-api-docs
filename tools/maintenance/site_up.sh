#!/usr/bin/env bash
# Simple script to place schoolbox into maintenance mode.
# 1. Ensures maintenance file is present
# 2. Reloads nginx config to detect the presence of the file

# Check we're running as root
if [[ "$EUID" -ne 0 ]]
  then echo "Please run as root, aborting"
  exit
fi

echo 'Taking Schoolbox online...';

# Get this file's directory, and derive the maintenance filename relatively from it
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
maintenanceFileName="$DIR/../../.maintenance";

# Does maintenance file already exist
echo "Checking for maintenance file '$maintenanceFileName'..."
if [[ -f ${maintenanceFileName} ]]; then
  echo "Maintenance file present, removing";
  if ! rm "${maintenanceFileName}"; then
    echo "Unable to remove maintenance file, exiting";
    exit 1;
  fi;
  echo "Maintenance file removed";
else
  echo "Maintenance file not present.";
fi;

# Production servers use 'open_file_cache', need to reload to detect file change
nginx -s reload
