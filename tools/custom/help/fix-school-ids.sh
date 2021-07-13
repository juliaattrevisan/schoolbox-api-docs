#!/bin/bash

readonly LOG="/usr/bin/alaress-logger"
readonly L_ERROR=400

declare package
declare instance_id;

error() {
  $LOG $L_ERROR "$1"
}

parse_args() {
  local errors='0'
  if [[ -z $INSTANCE_ID ]]; then
    error "No INSTANCE_ID PROVIDED"
    errors='1'
  fi
  local this_file;
  local this_dir;
  instance_id=$INSTANCE_ID
  this_file=$(readlink -f "$0");
  this_dir=${this_file%/*};
  site_root=$(readlink -f "$this_dir"/../../../)
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

fix_ids() {
  sql_host=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_host);
  sql_user=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_user);
  sql_pass=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_passwd);
  sql_db=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_db);

  echo $sql_host;

  if [[ ! $(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "UPDATE user SET synergy_id=id WHERE synergy_id IS NULL or synergy_id = ''") ]]; then
    error "failed to set IDs"
  fi
}

main (){
  parse_args "$@"
  fix_ids
}

main "$@"
