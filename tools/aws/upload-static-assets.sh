#!/usr/bin/env bash

# Global constants
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200
readonly L_ERROR=400
readonly ARG_HELP_SHORT="h"
readonly ARG_HELP_LONG="help"
readonly VAGRANT_YAML_KEY_AWS_BUCKET="aws_bucket:"
readonly VAGRANT_YAML_KEY_AWS_BUCKET_PREFIX="aws_bucket_prefix"

# Global vars
declare site_root
declare bucket_name
declare bucket_name
declare bucket_prefix

function log() {
  $LOG $L_INFO "$1"
}

function error() {
  $LOG $L_ERROR "$1"
}

function help_msg() {
  echo "Uploads static assets to S3"
  echo "Does not build/publish assets. You need to do that prior to invoking this tool with npm"
  echo ""
  echo "To use this script, you must first:"
  echo " Build static assets with \`npm run build\` or \`npm run publish\` as appropriate"
  echo ""
  echo "Options:"
  echo "  -$ARG_HELP_SHORT,--$ARG_HELP_LONG       Print this message and exit"
}

# Parse CLI args
function parse_args() {
  local errors='0'
  while [[ $# -gt 0 ]]; do
    case $1 in
    -"$ARG_HELP_SHORT" | --"$ARG_HELP_LONG")
      help_msg
      exit
      ;;
    *)
      error "Unrecognised option: '$1'"
      errors='1'
      shift # past argument
      ;;
    esac
  done

  if [[ $errors == 1 ]]; then
    error "Error: Incorrect options provided"
    help_msg
    exit 1
  fi
}

function derive_site_root() {
  file_path=$(readlink -f "$0")
  dir_path=${file_path%/*}
  site_root=$(readlink -f "$dir_path"/../../)
}

function load_config() {
  # Is the DOCKER env var there?
  # If so, load from docker.env: otherwise, from vagrant.yaml
  case "$HOST_TYPE" in
    docker)
      log "HOST_TYPE=docker: loading config from docker.default.env + docker.env"
      source "${site_root}/docker.default.env"
      source "${site_root}/docker.env"
      if [ ! -v CDN_HOST ]; then
        error "CDN_HOST not defined in docker.default.env or docker.env"
        exit 1
      fi
      if [ ! -v CDN_BUCKET ]; then
        error "CDN_BUCKET not defined in docker.default.env or docker.env"
        exit 1
      fi
      if [ ! -v CDN_BUCKET_PREFIX ]; then
        error "CDN_BUCKET_PREFIX not defined in docker.default.env or docker.env"
        exit 1
      fi
      bucket_host="${CDN_HOST}"
      bucket_name="${CDN_BUCKET}"
      bucket_prefix="${CDN_BUCKET_PREFIX}"
      ;;
    vagrant)
      log "HOST_TYPE=vagrant: loading config from vagrant.default.yaml + vagrant.yaml"
      bucket_host="https://s3-ap-southeast-2.amazonaws.com"
      bucket_name=$(read_var_from_vagrant_yaml "$VAGRANT_YAML_KEY_AWS_BUCKET")
      bucket_prefix=$(read_var_from_vagrant_yaml "$VAGRANT_YAML_KEY_AWS_BUCKET_PREFIX")
      if [[ -z $bucket_prefix ]]; then
        error "Bucket prefix not found in vagrant.yaml please add value for $VAGRANT_YAML_KEY_AWS_BUCKET_PREFIX with your firstname.lastname"
        exit 1
      fi
      ;;
    *)
      error "HOST_TYPE=${HOST_TYPE}: must be either vagrant or docker"
      exit 1
      ;;
    esac
}

# Function to fetch value from vagrant.yaml file by key
function read_var_from_yaml() {
  VAR=$(grep -E "^\s*$1" "$2" | xargs)
  IFS=":" read -ra VAR <<<"$VAR"
  echo "${VAR[1]}" | xargs
}
function read_var_from_vagrant_yaml() {
  file="$site_root"/vagrant.yaml
  if [[ ! -f $file ]]; then
    return
  fi
  var=$(read_var_from_yaml "$1" "$file")
  if [[ -z $var ]]; then
    file="$site_root"/vagrant.default.yaml
    var=$(read_var_from_yaml "$1" "$file")
  fi
  echo "$var" | xargs
}

function upload_assets() {
  log "Checking AWS credentials..."
  # TODO this doesn't properly check if the current profile has keys
  if ! aws configure list > /dev/null; then
    aws sso login --no-browser --use-device-code
  fi
  log "Uploading assets..."
  site_version=$(find "$site_root"/updates/ -mindepth 1 -maxdepth 1 -type d -printf %f\\n | sort -V | tail -n 1)
  site_package=${site_version}-0
  aws s3 sync "$site_root"/www/static/ s3://"$bucket_name"/"$bucket_prefix"/"$site_package"

  # TODO update config by connecting to correct database
  log "Updating config..."
  log "Setting 'site_package' to '$site_package'"
  "${site_root}/tools/instance-mariadb.sh" -e "UPDATE config SET value = '$site_package' WHERE name = 'site_package'"
  site_static_asset_prefix="$bucket_host/$bucket_name/$bucket_prefix/{site_package}"
  log "Setting 'site_static_asset_prefix' to '$site_static_asset_prefix'"
  "${site_root}/tools/instance-mariadb.sh" -e "UPDATE config SET value = '$site_static_asset_prefix' WHERE name = 'site_static_asset_prefix'"
  log "Setting 'site_static_asset_serve_from_cdn' to 1'"
  "${site_root}/tools/instance-mariadb.sh" -e "UPDATE config SET value = '1' WHERE name = 'site_static_asset_serve_from_cdn'"
}

function main() {
  derive_site_root "$@"
  parse_args "$@"
  load_config
  upload_assets
}

main "$@"
