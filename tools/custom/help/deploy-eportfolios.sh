#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;

readonly LOG="/usr/bin/alaress-logger"
readonly L_ERROR=400;

declare dryrun=''

error(){
  $LOG $L_ERROR "$1"
}

parse_args() {
  local errors='0'
  while [[ $# -gt 0 ]]; do
    case $1 in
    --dry-run)
      dryrun='--dry-run'
      shift
      ;;
    *)
      error "Unrecognised option: '$1'"
      errors='1'
      shift
      ;;
    esac
  done
  if [[ -z $INSTANCE_ID ]]; then
    error "No INSTANCE_ID PROVIDED"
    errors='1'
  fi
  local this_file;
  local this_dir;
  this_file=$(readlink -f "$0");
  this_dir=${this_file%/*};
  site_root=$(readlink -f "$this_dir"/../../../)

  if [[ ! -f "/etc/schoolbox/instances.ini" ]]; then
    error "File not found: '/etc/schoolbox/instances.ini'"
    errors='1'
  fi
  if [[ "$errors" == '1' ]]; then
    error "Errors detected, exiting"
    exit 1
  fi
}

deploy(){
  INSTANCE_ID=$INSTANCE_ID php /usr/share/schoolbox/tools/eportfolio/import.php -u3 -r1 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/schoolbox/tools/eportfolio/import.php -u3 -r16 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/schoolbox/tools/eportfolio/import.php -u3 -r13 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/schoolbox/tools/eportfolio/import.php -u3 -r18 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/schoolbox/tools/eportfolio/import.php -u3 -r6 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
}

main (){
  # Deprecation notice
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"
  echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
  echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"

  parse_args "$@"
  deploy

  # Deprecation notice
    echo -e "\033[1;33m/******************************************************************************************/\033[0m"
    echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
    echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
    echo -e "\033[1;33m/******************************************************************************************/\033[0m"
    echo -e "\033[1;33m/******************************************************************************************/\033[0m"
}

main "$@"
