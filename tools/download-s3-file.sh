#!/bin/bash
# Downloads files to specified cache dir
# Supports download from:
# 1. AWS S3 using aws cli + IAM credentials.
# 2. Generic HTTPS using no auth
# Description:
#       When provisioning or refreshing the data on an instance we need to fetch the latest version of the data
#       from our S3 storage.  The tool save-data.sh uploads data to this location.  This is the corresponding tool to
#       download that data.
#       The primary purpose is to access sales.tar.gz, initial.tar.gz or legacy.tar.gz but there are other files
#
# Usage:
#       tools/download-dataset.sh [--data-source [DATA_SOURCE]] --file-name [sales.tar.gz?versionId=1YDW] [--cache-dir [CACHE_DIR=/tmp]]
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
readonly ARG_DATA_SOURCE='data-source'
readonly ARG_FILE_NAME='file-name'
readonly ARG_CACHE_DIR='cache-dir'
readonly ARG_S3_REFERER='s3-referer'
readonly ARG_HELP_SHORT='h'
readonly ARG_HELP='help'
readonly CACHE_DIR_DEFAULT="/tmp"

# Global vars
declare file_name;                              # The name in the S3 bucket,     eg 'prefix/sales.tar.gz?versionId=abc'
declare file_name_short;                        # The name we use on local disk, eg 'prefix/sales.tar.gz'
declare file_basename                           # Prefix and version stripped,   eg 'sales.tar.gz'
declare cache_dir="$CACHE_DIR_DEFAULT"
declare s3_referer;
# Where to fetch files from:
# eg "https://s3-ap-southeast-2.amazonaws.com/schoolbox-data-temp"  - S3, use IAM credentials
declare data_source
declare s3_bucket

function log(){
  if [[ -f $LOG ]]; then
    $LOG $L_INFO "$1"
  else
    echo "$1"
  fi
}

function error(){
  if [[ -f $LOG ]]; then
    $LOG $L_ERROR "$1"
  else
    echo "$1"
  fi
}

function print_help(){
  echo "SYNOPSIS"
  echo "  Download a file from S3 to local cache dir. Checks file doesn't already exist in cache."
  echo "USAGE"
  echo "  $0 [OPTIONS] --$ARG_DATA_SOURCE <data-source> --$ARG_FILE_NAME <file-name>"
  echo "OPTIONS"
  echo "  -$ARG_HELP_SHORT,--$ARG_HELP                         Optional. Show this help message and exit"
  echo "  --$ARG_DATA_SOURCE <data-source>       Required. Where to download file from."
  echo "  --$ARG_CACHE_DIR <cache-dir>           Optional. Defaults to '$CACHE_DIR_DEFAULT'. Where to download file to."
  echo "  --$ARG_S3_REFERER <s3-referer>         Optional. Authentication token."
}


function parse_args(){
  local errors='0'
  while [[ $# -gt 0 ]]; do
    case $1 in
        -"$ARG_HELP_SHORT"|--"$ARG_HELP")
        print_help;
        exit
        ;;
        --"$ARG_DATA_SOURCE")
        data_source="$2"; shift; shift;
        ;;
        --"$ARG_S3_REFERER")
        s3_referer="$2"; shift; shift;
        ;;
        --"$ARG_FILE_NAME")
        file_name="$2"; shift; shift;
        file_name_short=$(echo "$file_name" | cut -d'?' -f1); # Strip off any '?versionId=abc' trailing part
        file_basename=$(basename "$file_name_short")
        ;;
        --"$ARG_CACHE_DIR")
        cache_dir=${2%/}           # trim trailing slashes
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
  if is_s3_data_source; then
    IFS='/' read -r -a array <<< "$data_source"
    s3_bucket="${array[-1]}"
  fi
  if [ $errors == '1' ]; then
    error "Errors detected, exiting";
    print_help
    exit 1
  fi
}

# Returns whether script has been invoked to fetch file from S3
function is_s3_data_source(){
  if [[ $data_source =~ ^https://s3-[a-z0-9-]+.amazonaws.com/ ]]; then
    return 0
  fi
  return 1
}

function validate_args(){
  if [[ -z "$data_source" ]]; then
    error "Please provide a data source to download from: e.g. --$ARG_DATA_SOURCE 'https://s3-ap-southeast-2.amazonaws.com/schoolbox-data'";
    errors='1'
  fi
  if [[ -z "$file_name" ]]; then
    error "Please provide a file to download: e.g. --$ARG_FILE_NAME 'sales.tar.gz'";
    errors='1'
  fi
  if [[ ! -d "$cache_dir" ]]; then
    error "Cache dir '$cache_dir' is not a directory";
    errors='1'
  fi
  if [[ $errors == '1' ]]; then
    error "Errors detected, exiting";
    print_help
    exit 1
  fi
}

# See:
# https://awscli.amazonaws.com/v2/documentation/api/latest/reference/s3/sync.html
# https://awscli.amazonaws.com/v2/documentation/api/latest/reference/s3/cp.html
function download_file_s3(){
  aws s3 sync "s3://$s3_bucket" "$cache_dir" --exclude='*' --include="$file_name_short"
  if [[ $? -ne 0 || ! -f $file_name_short ]] ; then
    # file may not exist locally yet, or sync may not be permitted:
    # either way fall back to cp
    error "Unable to sync file: $data_source/$file_name, falling back to cp"
    if ! aws s3 cp "s3://$s3_bucket/$file_name_short" "$cache_dir/$file_basename"; then
      error "Unable to cp file: $data_source/$file_name"
      exit 1;
    fi
  fi
}

# Downloads the file from S3
function download_file(){
  log "Downloading $data_source/$file_name";

  # use s3 sync to check if the file already exists and is up to date, or downloads new version
  download_file_s3
  log "Download completed";

}


# Main logic flow
function main(){
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  validate_args

  # If file already exists, check whether it is up to date
  if [[ ! -f "$cache_dir/$file_basename" ]]; then
    log "No cached file for '$file_basename' exists, downloading from upstream..."
  fi
  download_file
}

main "$@"
