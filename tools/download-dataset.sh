#!/bin/bash
# Downloads files from the schoolbox S3 data storage to specified cache dir
# Description:
#       When provisioning or refreshing the data on an instance we need to fetch the latest version of the data
#       from our S3 storage.  The tool save-data.sh uploads data to this location.  This is the corresponding tool to
#       download that data.
#       The primary purpose is to access sales.tar.gz, initial.tar.gz or legacy.tar.gz but there are other files
#
# Usage:
#       tools/download-data.sh --dataset [sales.tar.gz] [--cache-dir [CACHE_DIR=/tmp]]
#       The file will be downloaded to the specified cache directory, if the file already exists it will
#       check if a new version exits and overwrite if it does.
#
# @author      James Leckie <jleckie@schoolbox.com.au>
# @copyright   Copyright 2020, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

# Global const
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200;
readonly L_ERROR=400;
readonly DATA_SOURCE='https://s3-ap-southeast-2.amazonaws.com/schoolbox-data';

# Global vars
declare data_set_file;  # eg 'sales.tar.gz'
declare cache_dir='/tmp'

function log(){
  $LOG $L_INFO "$1"
}

function error(){
  $LOG $L_ERROR "$1"
}

function parse_args(){
  local errors='0'
  while [[ $# -gt 0 ]]; do
    case $1 in
        --dataset)
        data_set_file="$2"
        shift # past argument
        shift # past value
        ;;
        --cache-dir)
        cache_dir="$2"
        shift # past argument
        shift # past value
        ;;
        *)
        error "Unrecognised option: '$1'"
        errors='1'
        shift # past argument
        ;;
    esac
  done

  if [ -z "$data_set_file" ]; then
    error "Please provide a data_filename: e.g. --dataset sales.tar.gz";
    errors='1'
  fi
  if [ ! -d "$cache_dir" ]; then
    error "Cache dir '$cache_dir' is not a directory";
    errors='1'
  fi
  if [ $errors == '1' ]; then
    error "Errors detected, exiting";
    exit 1
  fi

}

# Checks if a cached file exists and is up to date.
# If so, file is touched and program exits immediately.
function check_cache(){
  local curl_head;
  if ! curl_head=$(curl -I "$DATA_SOURCE/$data_set_file"); then
    error "Unable to fetch curl head for $DATA_SOURCE/$data_set_file"
    exit 1;
  fi

  local http_response_code;
  http_response_code=$(echo "$curl_head" | grep -E 'HTTP/(1.0|1.1|2)' | cut -d' ' -f2);
  if [ "$http_response_code" != "200" ]; then
    error "Curl header HTTP response code error: $http_response_code)";
    exit 1;
  fi

  local etag;
  etag=$(echo "$curl_head" | grep -E '^ETag: "(\w+)"' | cut -d'"' -f2)
  if [ -z "$etag" ]; then
    error "Unable to parse etag from curl head"
    exit 1;
  fi

  local md5;
  if ! md5=$(md5sum "$cache_dir/$data_set_file" | cut -f 1 -d ' '); then
    error "Unable to determine md5 of $cache_dir/$data_set_file"
    exit 1;
  fi

  if [ "$md5" == "$etag" ]; then
    log "Current cached file is up to date: $cache_dir/$data_set_file"
    touch "$cache_dir/$data_set_file"
    exit;
  fi
  log "Current cached file is outdated: $cache_dir/$data_set_file"
}


# Downloads the file from S3
function download_file(){
  log "Downloading latest $DATA_SOURCE/$data_set_file";
  if ! wget --no-check-certificate --no-verbose "$DATA_SOURCE/$data_set_file" -O "$cache_dir/$data_set_file"; then
    error "Unable to download file: $DATA_SOURCE/$data_set_file"
    exit 1;
  fi
}


# Main logic flow
function main(){
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  # If file already exists, check whether it is up to date
  if [ -f "$cache_dir/$data_set_file" ]; then
    check_cache
  fi
  download_file
}

main "$@"
