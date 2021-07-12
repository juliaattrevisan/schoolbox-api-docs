#!/bin/bash
# 6 Teachers
# 7 Non-teaching Staff
# 8 Support Registered
# 13 Prospects
set -x

declare hashes=("02e74016681e294cc39938665bfb2b7fcac2d5ac" "07f4123f169da13a26389d3abdf61614aac68853"
  "1587ead84e1bafbbc4268effbaa4b50842f199db" "197050de22c7204c89efc25cfbfeeb736f6779b1" "3bac01cff1de2a5033f82f41e70d1aa3ffdf1fc1"
  "5015e1d3b9a592006ac81e74b458b7675b1fa1e3" "507ac64e7e1b63f737262f5e6117b3733ec217c6" "57daad1ba1e91ad89ee95e88dc8758e1e103ccf5"
  "5b49227a815bd1550d7d68ce3b4dd0445434aec1" "5beca697f1b3e97819875e4ec9181ac059bd3052" "6a3cc98bc5a368c5863b044141d373af0535cb19"
  "6abe6818b768d5ef6a542aea97a3b24f6ad07729" "6fde069c9acd30e06f1299ab1179e316e5526027" "736c61e52277e7998f4b8ef4ce398a0976ea75fe"
  "79d9c51137890548fe04d4eddabc689d84a2265c" "7a317fb7c0b5a7ceac33e2ef935865822c675695" "8594b3dac586fe8eb1ec752728fd2b0df807f0aa"
  "8b417bfd3ca9ab7f343601d3f2e4936d9e079644" "91fc410f242c5063d90cad8efcc43d57cb314df6" "92e0ce75acbba5a2a1ab02906b0d5099c26fa587"
  "9a4ed3464a1582919e94b6024810e2a3f4f14318" "9c08f23968bac331b83b3128a2252cee66af69af" "c3dbacb928585b97a9ef9c9f44fcfc8213ad2d73"
  "ccfa160314ad84fa2b2e344a8e2400d4ded64260" "ceec9345cd17f93bac3cc96e5938e695de5082fd" "d0f98a27ca59dad33fbaa61b726e1d4c78884bcf"
  "d57e79d2a568b4e93d492582907de8f20f8f24ef" "dbd2e21010b4706d6f8baf8c63cda651655e1d24" "dd87aaca3586607382ad17c90475e06bc68909b7"
  "e7d4dfab9492feb7f049b8772998402145a6e15e" "ee1a98bdb62736c687ead53f9b4e4b102a071b7a" "f0b1317c9f0811a8b10a43f9a8b4d2ca11da191c"
  "f9fa0b4fe67c890e089dc041f379ef61a76b5879" "fe3c0af58f070998a4ce7dd2823f707374f1241c"
)

readonly LOG="/usr/bin/alaress-logger"
readonly L_INFO=200
readonly L_ERROR=400

declare package

# Consistently log messages
log() {
  $LOG $L_INFO "$1"
}
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

set_image() {
  sql_host=$($INSTANCE_ID schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_host)
  sql_user=$($INSTANCE_ID schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_user)
  sql_pass=$($INSTANCE_ID schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_passwd)
  sql_db=$($INSTANCE_ID schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_db)

  for i in $(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "
    select synergy_id from user where group_id in ('6','7','8','13') and synergy_id is not null and synergy_id != '' and " 2>/dev/null); do
    rand=$((RANDOM % ${#hashes[@]} + 1))
    hash=${hashes[$rand]}
    log "setting profile image for user $i as $hash"
    if [[ ! $(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "update user set portrait_hash = $hash where synergy_id  = $1") ]]; then
      error "Couldn't set image for user $i"
    fi
  done
}

main (){
  parse_args "$@"
  set_image
}

main "$@"
