#!/bin/bash

# Global constants
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200;
readonly L_ERROR=400;
readonly ARG_HELP_SHORT="h"
readonly ARG_HELP_LONG="help"
readonly ARG_AWS_BUCKET="bucket"
readonly ARG_AWS_ACCESS_KEY_ID="access-key"
readonly ARG_AWS_SECRET_ACCESS_KEY="secret-key"
readonly VAGRANT_YAML_KEY_AWS_ACCESS_KEY="aws_iam_key"      # Name of var in vagrant.yaml file
readonly VAGRANT_YAML_KEY_AWS_SECRET_KEY="aws_iam_secret"   # Name of var in vagrant.yaml file

# Global vars
declare site_root
declare aws_bucket
declare aws_access_key_id
declare aws_secret_access_key

function log(){
  $LOG $L_INFO "$1"
}

function error(){
  $LOG $L_ERROR "$1"
}

function check_run_as_root(){
  if [[ $EUID -ne 0 ]]; then
     error "This script must be run as root to enable install of awscli package"
     exit 1
  fi
}

# Install awscli v2, removing v1 if present
function install_dependencies(){
  local remove_v1
  local install_v2

  if ! type aws &>/dev/null; then
    remove_v1=0
    install_v2=1
  else
    aws_version=$(aws --version 2>/dev/null | grep -Eo "aws-cli/[0-9.]+" | cut -d '/' -f 2)
    if [[ $aws_version =~ ^1. ]]; then
      remove_v1=1
      install_v2=1
    else
      remove_v1=0
      install_v2=0
    fi
  fi

  if [[ $remove_v1 == 1 ]]; then
    log "Removing awscli v1..."
    apt remove awscli -y
  fi

  if [[ $install_v2 == 1 ]]; then
    pwd=$(pwd)
    cd /tmp || exit 1
    log "Installing awscli v2..."
    curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
    unzip awscliv2.zip
    ./aws/install
    rm awscliv2.zip
    rm -r aws/
    cd "$pwd" || exit 1
  fi
}

function help_msg(){
  echo "Uploads static assets to S3"
  echo "Does not build/publish assets. You need to do that prior to invoking this tool with npm"
  echo "Requires AWS S3 credentials to a development account. These credentials will be derived in following order:"
  echo "1. Via CLI args"
  echo "2. From vagrant.yaml file"
  echo "3. Prompted at run-time"
  echo ""
  echo "To use this script, you must first:"
  echo " 1. Create a development AWS account and provision IAM Access + Secret key pair"
  echo " 2. Create a S3 bucket with public READ access + public CORS"
  echo " 3. Build static assets with \`npm run build\` or \`npm run publish\` as appropriate"
  echo ""
  echo "Options:"
  echo "  -$ARG_HELP_SHORT,--$ARG_HELP_LONG       Print this message and exit"
  echo "  --$ARG_AWS_BUCKET <BUCKET_NAME>        Required. Bucket to upload assets and point static asset URL to."
  echo "  --$ARG_AWS_ACCESS_KEY_ID <ACCESS_KEY>"
  echo "  --$ARG_AWS_SECRET_ACCESS_KEY <SECRET_KEY>"
}


# Parse CLI args
function parse_args(){
  local errors='0'
  while [[ $# -gt 0 ]]; do
    case $1 in
    -"$ARG_HELP_SHORT"|--"$ARG_HELP_LONG")
      help_msg
      exit;
      ;;
    --"$ARG_AWS_BUCKET")
      aws_bucket="$2"
      shift 2
      ;;
    --"$ARG_AWS_ACCESS_KEY_ID")
      aws_access_key_id="$2"
      shift 2
      ;;
    --"$ARG_AWS_SECRET_ACCESS_KEY")
      aws_secret_access_key="$2"
      shift 2
      ;;
    *)
      error "Unrecognised option: '$1'"
      errors='1'
      shift # past argument
      ;;
  esac
  done

  if [[ -z $aws_bucket ]]; then
    error "You must specify a bucket name using the --$ARG_AWS_BUCKET flag"
    errors='1'
  fi

  if [[ $errors == 1 ]]; then
    error "Error: Incorrect options provided"
    help_msg
    exit 1
  fi
}

function derive_site_root(){
  file_path=$(readlink -f "$0");
  dir_path=${file_path%/*}
  site_root=$(readlink -f "$dir_path"/../../);
}

# Function to fetch value from vagrant.yaml file by key
function read_var_from_vagrant_yaml() {
  file="$site_root"/vagrant.yaml
  if [[ ! -f $file ]]; then
    return
  fi
  VAR=$(grep -E "^\s*$1" "$file" | xargs)
  IFS=":" read -ra VAR <<< "$VAR"
  echo "${VAR[1]}"
}

function load_access_and_secret_keys(){
  # Access Key
  if [[ -z $aws_access_key_id ]]; then
    aws_access_key_id=$(read_var_from_vagrant_yaml $VAGRANT_YAML_KEY_AWS_ACCESS_KEY)
  fi
  while [[ -z $aws_access_key_id ]]; do
     read -r -p "AWS Access key: " aws_access_key_id
     if [[ -z $aws_access_key_id ]]; then
        echo "Invalid. Please provide a value" >&2
     fi
  done

  # Secret Key
  if [[ -z $aws_secret_access_key ]]; then
    aws_secret_access_key=$(read_var_from_vagrant_yaml $VAGRANT_YAML_KEY_AWS_SECRET_KEY)
  fi
  while [[ -z $aws_secret_access_key ]]; do
     read -r -p "AWS Secret key: " aws_secret_access_key
     if [[ -z $aws_secret_access_key ]]; then
        echo "Invalid. Please provide a value" >&2
     fi
  done
}


function upload_assets(){
  log "Uploading assets..."
  site_version=$(find "$site_root"/updates/ -mindepth 1 -maxdepth 1 -type d -printf %f\\n | sort -V | tail -n 1)
  site_package=${site_version}-0

  export AWS_ACCESS_KEY_ID="$aws_access_key_id"
  export AWS_SECRET_ACCESS_KEY="$aws_secret_access_key"
  aws s3 sync "$site_root"/www/static/ s3://"$aws_bucket"/"$site_package"

  log "Updating config..."
  log "Setting 'site_package' to '$site_package'"
  mysql schoolbox -e "UPDATE config SET value = '$site_package' WHERE name = 'site_package'"
  site_static_asset_prefix="https://s3-ap-southeast-2.amazonaws.com/$aws_bucket/{site_package}"
  log "Setting 'site_static_asset_prefix' to '$site_static_asset_prefix'"
  mysql schoolbox -e "UPDATE config SET value = '$site_static_asset_prefix' WHERE name = 'site_static_asset_prefix'"
  log "Setting 'site_static_asset_serve_from_cdn' to 1'"
  mysql schoolbox -e "UPDATE config SET value = '1' WHERE name = 'site_static_asset_serve_from_cdn'"
}


function main(){
  check_run_as_root
  install_dependencies
  derive_site_root "$@"
  parse_args "$@"
  load_access_and_secret_keys
  upload_assets
}


main "$@"
