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
readonly ARG_IAM_ACCESS_KEY='iam-access-key'
readonly ARG_IAM_SECRET_KEY='iam-secret-key'
readonly ARG_HELP_SHORT='h'
readonly ARG_HELP='help'
readonly CACHE_DIR_DEFAULT="/tmp"

# Global vars
declare file_name;                              # The name in the S3 bucket,     eg 'prefix/sales.tar.gz?versionId=abc'
declare file_name_short;                        # The name we use on local disk, eg 'prefix/sales.tar.gz'
declare file_basename                           # Prefix and version stripped,   eg 'sales.tar.gz'
declare cache_dir="$CACHE_DIR_DEFAULT"
declare s3_referer;
declare iam_access_key
declare iam_secret_key
# Where to fetch files from:
# eg "https://s3-ap-southeast-2.amazonaws.com/schoolbox-data-temp"  - S3, use IAM credentials
# eg "https://gw-mel.alaress.com.au/sandbox-data"                   - not S3, no auth
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
  echo "  --$ARG_DATA_SOURCE <data-source>       Required. Where to download file from. Either S3 or Camberwell office caching proxy."
  echo "  --$ARG_CACHE_DIR <cache-dir>           Optional. Defaults to '$CACHE_DIR_DEFAULT'. Where to download file to."
  echo "  --$ARG_S3_REFERER <s3-referer>         Optional. Authentication token."
  echo "  --$ARG_IAM_ACCESS_KEY <iam-access-key> Required to download from S3 data sources"
  echo "  --$ARG_IAM_SECRET_KEY <iam-secret-key> Required to download from S3 data sources"
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
        --"$ARG_IAM_ACCESS_KEY")
        iam_access_key="$2"; shift; shift;
        ;;
        --"$ARG_IAM_SECRET_KEY")
        iam_secret_key="$2"; shift; shift;
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

  # Is data source S3 or generic HTTP?
  if is_s3_data_source; then
    if [[ -z "$iam_access_key" ]]; then
      error "Please provide an IAM Access Key to authenticate to AWS with using: e.g. --$ARG_IAM_ACCESS_KEY 'xxx'";
      errors='1'
    fi
    if [[ -z "$iam_secret_key" ]]; then
      error "Please provide an IAM Secret Key to authenticate to AWS with using: e.g. --$ARG_IAM_SECRET_KEY 'xxx'";
      errors='1'
    fi
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

# Checks whether local file's md5 matches $etag. If so, exits.
function check_cache_file_hash(){
  etag=$1

  local md5;
  if ! md5=$(md5sum "$cache_dir/$file_basename" | cut -f 1 -d ' '); then
    error "Unable to determine md5 of $cache_dir/$file_basename"
    exit 1;
  fi

  if [ "$md5" == "$etag" ]; then
    log "Current cached file is up to date: $cache_dir/$file_basename"
    touch "$cache_dir/$file_basename"
    exit 0;
  fi
  log "Current cached file is outdated: $cache_dir/$file_basename, downloading from upstream..."
}

# See https://docs.aws.amazon.com/AmazonS3/latest/API/API_HeadObject.html
function check_cache_s3(){
  resource="/$s3_bucket/$file_name_short"
  dateValue="$(date -u +'%a, %d %b %Y %H:%M:%S GMT')"
  contentType="application/x-compressed-tar"
  stringToSign="HEAD\n\n${contentType}\n\nx-amz-date:${dateValue}\n${resource}"
  signature=$(/bin/echo -en "$stringToSign" | openssl sha1 -hmac "${iam_secret_key}" -binary | base64)

  local curl_head;
  if ! curl_head=$(curl --head --silent  --show-error -H "Host: ${s3_bucket}.s3.amazonaws.com" \
    -H "x-amz-date: ${dateValue}" \
    -H "Content-Type: ${contentType}" \
    -H "Authorization: AWS ${iam_access_key}:${signature}" \
    "https://$s3_bucket.s3.amazonaws.com/$file_name_short"); then
    error "Unable to fetch curl head for $data_source/$file_name"
    exit 1;
  fi

  local http_response_code;
  http_response_code=$(echo "$curl_head" | grep -E 'HTTP/(1.0|1.1|2)' | cut -d' ' -f2);
  if [ "$http_response_code" != "200" ]; then
    error "Curl header HTTP response code error: $http_response_code";
    exit 1;
  fi

  local etag;
  etag=$(echo "$curl_head" | grep -E '^ETag: "(\w+)"' | cut -d'"' -f2)
  if [ -z "$etag" ]; then
    error "Unable to parse etag from curl head"
    exit 1;
  fi

  check_cache_file_hash "$etag"
}

# TODO. Need to test this and probably update it to work with Melbourne office proxy caching server
function check_cache_proxy_server(){
  local curl_head;
  if ! curl_head=$(curl -I "$data_source/$file_name" 2>/dev/null); then
    error "Unable to fetch curl head for $data_source/$file_name"
    exit 1;
  fi

  local http_response_code;
  http_response_code=$(echo "$curl_head" | grep -E 'HTTP/(1.0|1.1|2)' | cut -d' ' -f2);
  if [ "$http_response_code" != "200" ]; then
    error "Curl header HTTP response code error: $http_response_code";
    exit 1;
  fi

  local etag;
  etag=$(echo "$curl_head" | grep -E '^ETag: "(\w+)"' | cut -d'"' -f2)
  if [ -z "$etag" ]; then
    error "Unable to parse etag from curl head"
    exit 1;
  fi

  check_cache_file_hash "$etag"
}

# Checks if a cached file exists and is up to date.
# If so, file is touched and program exits immediately.
function check_cache(){
  if is_s3_data_source; then
    check_cache_s3
  else
    check_cache_proxy_server
  fi
}

# See https://docs.aws.amazon.com/AmazonS3/latest/API/API_GetObject.html
function download_file_s3(){
  resource="/$s3_bucket/$file_name_short"
  dateValue="$(date -u +'%a, %d %b %Y %H:%M:%S GMT')"
  contentType="application/x-compressed-tar"
  stringToSign="GET\n\n${contentType}\n\nx-amz-date:${dateValue}\n${resource}"
  signature=$(/bin/echo -en "$stringToSign" | openssl sha1 -hmac "${iam_secret_key}" -binary | base64)

  if ! curl --silent --show-error -H "Host: ${s3_bucket}.s3.amazonaws.com" \
    -H "x-amz-date: ${dateValue}" \
    -H "Content-Type: ${contentType}" \
    -H "Authorization: AWS ${iam_access_key}:${signature}" \
    "https://$s3_bucket.s3.amazonaws.com/$file_name_short" \
    -o "$cache_dir/$file_basename"; then
    error "Unable to download file: $data_source/$file_name"
    exit 1;
  fi
}

# TODO this still needs to be tested against Melbourne caching proxy server
function download_file_proxy_server(){
  if ! wget -q --no-check-certificate --no-verbose --header="Referer: $s3_referer" "$data_source/$file_name" -O "$cache_dir/$file_name_short"; then
    error "Unable to download file: $data_source/$file_name"
    exit 1;
  fi
}


# Downloads the file from S3
function download_file(){
  log "Downloading $data_source/$file_name";

  if is_s3_data_source; then
    download_file_s3
  else
    download_file_proxy_server
  fi
}


# Main logic flow
function main(){
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  validate_args

  # If file already exists, check whether it is up to date
  if [[ ! -f "$cache_dir/$file_basename" ]]; then
    log "No cached file for '$file_basename' exists, downloading from upstream..."
  else
    log "Cache file found for '$file_basename' checking for upstream changes..."
    check_cache
  fi
  download_file
}

main "$@"
