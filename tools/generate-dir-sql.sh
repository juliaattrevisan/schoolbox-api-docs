#!/bin/bash
# Generate Directory SQL Script
# Description:
#   Generates the SQL required to update the Schoolbox config table with
#   the locations of the template and data directories.
#   The result is sent to STDOUT
# Usage:
#   generate-data-dir-sql.sh [INSTANCE_DATA_DIR]
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   Copyright 2020, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

readonly COLOUR_RED="\033[38;5;1m";
readonly COLOUR_DEFAULT="\033[39m";

if [ $# -ne 1 ]; then
  echo -e "${COLOUR_RED}Invalid parameters detected${COLOUR_DEFAULT}" >&2
  echo -e "${COLOUR_RED}Please provide the instance's data dir, eg${COLOUR_DEFAULT}" >&2
  echo -e "${COLOUR_RED}    $0 /var/lib/schoolbox[dev]/a1b2c3d4${COLOUR_DEFAULT}" >&2
  exit 1
fi

# For onPrem instances, this will likely not contain the instanceId.
# For cloud instances, this must contain an instanceId
# Strip the trailing slash if present, eg '/var/lib/schoolbox/a1b2c3d4/'
# shellcheck disable=SC2001
instance_data_dir="$(echo "$1" | sed 's:/*$::')"

# While technically we can probably share some of these directories across all instances on a site,
# for simplicity and safety for now we will make them all instance-specific.
# If we do wish to share some dirs across the site, this script will need to accept two args [SITE_DATA_DIR], [INSTANCE_ID]

# Instance-specific configs
echo "UPDATE config SET value='$instance_data_dir/purifier'       WHERE name='purifier_cache_dir';"
echo "UPDATE config SET value='$instance_data_dir/twig'           WHERE name='twig_cache_dir';"
echo "UPDATE config SET value='$instance_data_dir/files'          WHERE name='backup_dir';"
echo "UPDATE config SET value='$instance_data_dir/cache/zip'      WHERE name='zip_dir';"
echo "UPDATE config SET value='$instance_data_dir/cache/stream'   WHERE name='stream_dir';"
echo "UPDATE config SET value='$instance_data_dir/cache/portrait' WHERE name='portrait_dir';"
echo "UPDATE config SET value='$instance_data_dir/cache/logs'     WHERE name='logs_dir';"
echo "UPDATE config SET value='$instance_data_dir/storage'        WHERE name='storage_dir';"
