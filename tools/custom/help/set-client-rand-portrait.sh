#!/bin/bash
# 6 Teachers
# 7 Non-teaching Staff
# 8 Support Registered
# 13 Prospects

declare hashes=(
"fc05e59b526bf2903f79c949318a0c305be79167" # SchoolboxHelp_UserProfilePics-01.png
"c654809efc2d91d11558e5582583e8adb73844b2" # SchoolboxHelp_UserProfilePics-02.png
"6958fd351da1184add20362c8f4d41f1c4933b35" # SchoolboxHelp_UserProfilePics-03.png
"4170ad26f3eefbc5448b6fc9708fd517579fc576" # SchoolboxHelp_UserProfilePics-04.png
"5d4b8853f0740375fe6b99dc43d6e209df42c211" # SchoolboxHelp_UserProfilePics-05.png
"0c6ed1e8a97032706cd7eaa25fc91f725f2ad222" # SchoolboxHelp_UserProfilePics-06.png
"ec1cc5ecd0e2456ddbf859d302eb6749ae10411f" # SchoolboxHelp_UserProfilePics-07.png
"a22c17b8cd8d4cdd4db1594d4dbd23f9b45b40d4" # SchoolboxHelp_UserProfilePics-08.png
"42f2b15d46bb462315b2466173acb988031219f7" # SchoolboxHelp_UserProfilePics-09.png
"8ed390e83f52e851fe6c9b258af5662ab0bc0bbf" # SchoolboxHelp_UserProfilePics-10.png
"32b4e1bb392cdc6226872b3aaa125e8061c9e990" # SchoolboxHelp_UserProfilePics-11.png
"003b6d4d767375ce6ad54d2535fde0a356b8889c" # SchoolboxHelp_UserProfilePics-12.png
"376649bc0c320a49fdd0dcc90ffb929c5d6e7ff4" # SchoolboxHelp_UserProfilePics-13.png
"b33abf7c26f3abf69296ad8c574c66643a44ba40" # SchoolboxHelp_UserProfilePics-14.png
"f91ffb56c77f6d2a3e9b329e7dc4e91e461a6c2f" # SchoolboxHelp_UserProfilePics-15.png
"53867f96de5634bb4ce74a1471cbcf5ce1ba306b" # SchoolboxHelp_UserProfilePics-16.png
"be0d54c95b4cb2b1345c4fbbe3b9efadb0c36108" # SchoolboxHelp_UserProfilePics-17.png
"743858870fa90cfbbddaa2a232104a5e8830bfd1" # SchoolboxHelp_UserProfilePics-18.png
"7289b576dd06b1f74dddacd96678afa0f32f18e6" # SchoolboxHelp_UserProfilePics-19.png
"b4b38047a9768abc061aa29531ed6870de311dff" # SchoolboxHelp_UserProfilePics-20.png
"dd981bedbbb903af8799956c8df2a26643c976e2" # SchoolboxHelp_UserProfilePics-21.png
"fd5b5ff90bbfcddee4556a39f942e5ac14e4085b" # SchoolboxHelp_UserProfilePics-22.png
"ff0d5bcccc42b2b84387625abd3a1703e44c49f5" # SchoolboxHelp_UserProfilePics-23.png
"1ab21eb7c970bae84a670241fee14662745f6bc7" # SchoolboxHelp_UserProfilePics-24.png
"2e504d319218d2286e32d80fdf09656e74401474" # SchoolboxHelp_UserProfilePics-25.png
"3b8ba5f999d0bcefbae25235c75ba776f849a491" # SchoolboxHelp_UserProfilePics-26.png
"222c5a2a70d3114dd6f765c19b704c3b997916b1" # SchoolboxHelp_UserProfilePics-27.png
"b925152cf14b6468302f59763b98e7c0ceaeb451" # SchoolboxHelp_UserProfilePics-28.png
"6aca4c25318369f8f1c70da1c02110458a6d20a7" # SchoolboxHelp_UserProfilePics-29.png
"e3b1faa56f7fbb2f395dc7bb45c99fc93350b55e" # SchoolboxHelp_UserProfilePics-30.png
"096b155bea7b0b9be89d9d5731d26e8ad63c13b4" # SchoolboxHelp_UserProfilePics-31.png
"366813dd0613786b56890e3470c176e35e181a49" # SchoolboxHelp_UserProfilePics-32.png
"b8bcd1aae77c9ec1bcff088153801dd6e5f95d49" # SchoolboxHelp_UserProfilePics-33.png
"cec280dd71afa14c92a55940b84485bc98c19c8e" # SchoolboxHelp_UserProfilePics-34.png
"89a871daa4da3ed9c86e11a8dadac2a4cb6dbc63" # SchoolboxHelp_UserProfilePics-35.png
"297ea6d78f21775b327e65e6f13221a466571373" # SchoolboxHelp_UserProfilePics-36.png
"6ab0cdd7d462cdf067ba26f144b47dcf4f0c1259" # SchoolboxHelp_UserProfilePics-37.png
"34e76d89d6e9deaf3bcdb5cc94a1bf6f77a0ed9b" # SchoolboxHelp_UserProfilePics-38.png
"95ff7030c1acb755ca360920a0a06db36e081a02" # SchoolboxHelp_UserProfilePics-39.png
"001a6fa6dc6c313c302063fc4ad98dc578969dd4" # SchoolboxHelp_UserProfilePics-40.png
"2c1cff7bcdb9359654918d94295144caf08a1344" # SchoolboxHelp_UserProfilePics-41.png
"804806e2b332cb223c890cb6a6a32032647d04d7" # SchoolboxHelp_UserProfilePics-42.png
"d5398633bde80a70af9be637f5988fa1eedd070f" # SchoolboxHelp_UserProfilePics-43.png
"c647fd2f4c8fc5c2b72c82d548b7af6e6b33791b" # SchoolboxHelp_UserProfilePics-44.png
"04e0a3293e4d5df37df70b984ee7c5effe4d099c" # SchoolboxHelp_UserProfilePics-45.png
"4aed1575f9f637489b1e01e94ffebcb1e76b34d6" # SchoolboxHelp_UserProfilePics-46.png
"4cb13f51aa7f3a2e6c83103911f0074c964b1ca4" # SchoolboxHelp_UserProfilePics-47.png
"7be2d5eea2c173808bf8415d25bf513de9090c77" # SchoolboxHelp_UserProfilePics-48.png
"f5c3c60fc984f4d169098a2659dbb30435e4ddd7" # SchoolboxHelp_UserProfilePics-49.png
"378ed0829821aff45b191bbfa26cb36da588b40f" # SchoolboxHelp_UserProfilePics-50.png
"028c07e5692db41ac36deaab3a77037ab1287cf1" # SchoolboxHelp_UserProfilePics-51.png
"116c1257e9275e989a2525e6e7c078ce4b1febf1" # SchoolboxHelp_UserProfilePics-52.png
"d4a8bc84ce9944d39b47560f207f62fcdf971729" # SchoolboxHelp_UserProfilePics-53.png
"a9aec12037ec1d67854481b0e6abdc6413a122c7" # SchoolboxHelp_UserProfilePics-54.png
"042a9134a6ae94efe4a19afc67119a05b46118f5" # SchoolboxHelp_UserProfilePics-55.png
"42911eeffad6d9073ac9634221cbbf9596826666" # SchoolboxHelp_UserProfilePics-56.png
"75c1b213a73a230e42e588be4d6cd388643fa7ca" # SchoolboxHelp_UserProfilePics-57.png
"62a0b1cf068af1447c49aab8129813ec5c41200d" # SchoolboxHelp_UserProfilePics-58.png
"82b7588fea3c0a74f465a07b562b6ca3a2a3133f" # SchoolboxHelp_UserProfilePics-59.png
"c30af4b5a2c58e7c690d80e18673ab617bf7e433" # SchoolboxHelp_UserProfilePics-60.png
"b366283d4dd07d39c25d6761961631f9164a0fef" # SchoolboxHelp_UserProfilePics-61.png
"40a2ea92b6cae3eedca7524bc9cecb19654c05f8" # SchoolboxHelp_UserProfilePics-62.png
"4b05870510ff9962c9a8c39b1e9bbde367edabe7" # SchoolboxHelp_UserProfilePics-63.png
"0f5a8ce5051e417ae92cb02a6875ae246bb991b9" # SchoolboxHelp_UserProfilePics-64.png
"00de3ea3852009a41e3c72c192c5c82ae2860869" # SchoolboxHelp_UserProfilePics-65.png
"2d911d85db599fad0a888f0ffcba32a9c3d50427" # SchoolboxHelp_UserProfilePics-66.png
"5ad3cc459343fb3d46c07b42d484389e2cebd565" # SchoolboxHelp_UserProfilePics-67.png
"69431d39d3dc7b91c03edc8e7406a073118b1248" # SchoolboxHelp_UserProfilePics-68.png
"615aaaaec02993530e0ed01eeccfd165a6eb1f0c" # SchoolboxHelp_UserProfilePics-69.png
"680fa52409d6f1e3bcd6bf83dfb31253c85e9b45" # SchoolboxHelp_UserProfilePics-70.png
"e999ae2a0c0f4c49ad50e7801f9f0800d685085d" # SchoolboxHelp_UserProfilePics-71.png
"5c4d125ac67753c5c3854ab6a15137c59d35d19c" # SchoolboxHelp_UserProfilePics-72.png
"3baa7d06cb3b73f81e42d4adbf0b05c0ebe562d8" # SchoolboxHelp_UserProfilePics-73.png
"4fedce70dd7e22ba0841fbbca6ef59b5972aba9f" # SchoolboxHelp_UserProfilePics-74.png
"acbf63ff94521def2a3bde8277fecea2a2c9a765" # SchoolboxHelp_UserProfilePics-75.png
"bb5b575ddc7fcfa35f33112d52169d0e01c39f00" # SchoolboxHelp_UserProfilePics-76.png
"697b0f78a135ff3384df09870a4dd072e999ddd4" # SchoolboxHelp_UserProfilePics-77.png
"64431d6cbd45f18ebfd439e9e6fe2b1f4983b14e" # SchoolboxHelp_UserProfilePics-78.png

)

readonly LOG="/usr/bin/alaress-logger"
readonly L_INFO=200
readonly L_ERROR=400


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

  if [[ ! -f "/etc/schoolbox/instances.ini" ]]; then
    error "File not found: '/etc/schoolbox/instances.ini'"
    errors='1'
  fi
  if [[ "$errors" == '1' ]]; then
    error "Errors detected, exiting"
    exit 1
  fi
}

set_image() {
  sql_host=$(schoolbox-read-instances --option SQL_host)
  sql_user=$(schoolbox-read-instances --option SQL_user)
  sql_pass=$(schoolbox-read-instances --option SQL_passwd)
  sql_db=$(schoolbox-read-instances --option SQL_db)

  for i in $(mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "SELECT synergy_id FROM user WHERE group_id in ('6','7','8','13') AND synergy_id IS NOT NULL AND synergy_id != ''" 2>/dev/null); do
    rand=$((RANDOM % ${#hashes[@]} + 1))
    hash=${hashes[$rand]}
    log "setting profile image for user $i as $hash"
    if [[ ! $(mariadb -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "UPDATE user SET portrait_hash = '$hash' WHERE synergy_id = '$i'") ]]; then
      error "Couldn't set image for user $i"
    fi
  done
}

main (){
  # Deprecation notice
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"
  echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
  echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"

  parse_args "$@"
  set_image

  # Deprecation notice
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"
  echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
  echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"
  echo -e "\033[1;33m/******************************************************************************************/\033[0m"
}

main "$@"
