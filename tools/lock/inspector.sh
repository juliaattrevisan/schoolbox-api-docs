#! /bin/bash
# Detects if a specified lockfile is created and if not creates it
# Usage: lockfile_detect.sh --lockfile <lockfile> [--help] [--rm]
# @copyright   Copyright 2022, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
#

readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400
readonly ARG_LOCKFILE="--lockfile"
readonly ARG_REMOVE="--rm"
readonly ARG_HELP="--help"

declare lockfile
declare remove=false

function log(){
  $LOG $L_INFO "$1"
}

function warn(){
  $LOG $L_WARN "$1"
}

function error(){
  $LOG $L_ERROR "$1"
}

function parse_args() {
    while [[ $# -gt 0 ]]; do
        key="$1"
        case $key in
            "$ARG_LOCKFILE")
                lockfile="$2"
                shift
                shift
                ;;
            "$ARG_REMOVE")
                remove=true
                shift
                ;;
            "$ARG_HELP")
                help
                exit 0
                ;;
            *)
                error "Unknown argument: $key"
                help
                exit 1
                ;;
        esac
    done
}

function validate_args() {
    if [[ -z "$lockfile" ]]; then
        error "Lockfile not specified"
        help
        exit 1
    fi
}

function help() {
    echo "Usage: $0 $ARG_LOCKFILE <lockfile> $ARG_SCRIPTNAME <scriptname> [$ARG_REMOVE] [$ARG_HELP]"
    echo "  --lockfile <lockfile>  The lockfile to create"
    echo "  --rm                   Remove the lockfile if it exists"
    echo "  --help                 Print this help message"
}

function check_and_create_lockfile() {
  scriptname=$(ps -f -o cmd= $PPID | grep -o '[^/]*$')
  if [[ -f $lockfile ]]; then
      read -r pid < "$lockfile"
      if [[ -d /proc/$pid ]] && pgrep --full "$scriptname" | grep -qE "$pid"; then
        error "Lockfile $lockfile is already in use by $scriptname"
        exit 1
      fi
  fi
  echo $PPID > "$lockfile"
}

function main() {
  parse_args "$@"
  validate_args
  if [[ "$remove" == true ]]; then
      rm -f "$lockfile"
  else
      check_and_create_lockfile
  fi
  exit 0
}

main "$@"
