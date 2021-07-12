#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;

readonly LOG="/usr/bin/alaress-logger"
readonly L_ERROR=400;

declare package
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
  site_root=$(readlink -f "$this_dir"/../../)
  if [[ ${site_root##*/} == 'schoolboxdev' ]]; then
    package='schoolboxdev'
  else
    package='schoolbox'
  fi
  if [[ ! -f "/etc/$package/instances.ini" ]]; then
    error "File not found: '/etc/$package/instances.ini'"
    errors='1'
  fi
  if [[ "$errors" == '1' ]]; then
    error "Errors detected, exiting"
    exit 1
  fi
}

deploy(){
  INSTANCE_ID=$INSTANCE_ID php /usr/share/"$package"/tools/eportfolio/import.php -u3 -r1 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/"$package"/tools/eportfolio/import.php -u3 -r16 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/"$package"/tools/eportfolio/import.php -u3 -r13 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/"$package"/tools/eportfolio/import.php -u3 -r18 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
  INSTANCE_ID=$INSTANCE_ID php /usr/share/"$package"/tools/eportfolio/import.php -u3 -r6 -p800 "$dryrun" --skip-duplicates --enable-eportfolio;
}

main (){
  parse_args "$@"
  deploy
}

main "$@"
