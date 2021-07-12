#!/bin/bash

readonly LOG="/usr/bin/alaress-logger"
readonly L_INFO=200
readonly L_ERROR=400

declare verbose=0
declare package           # One of [schoolbox|schoolboxdev]
declare site_root         # No trailing slash, eg '/vagrant'
declare instance_id;

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

review() {
  sql_host=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_host)
  sql_user=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_user)
  sql_pass=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_passwd)
  sql_db=$(INSTANCE_ID=$instance_id schoolbox-read-instances --ini "/etc/$package/instances.ini" --option SQL_db)

  check=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "
SELECT
    aa.attempt_id
FROM
    assess_attempt aa
        JOIN
    assess a ON aa.assess_id = a.assess_id
        AND a.availability = 'assess'
        JOIN
    user u ON aa.user_id = u.id
        AND u.deleted_at IS NULL
        JOIN
    folder c ON a.category_id = c.id
        AND c.deleted_at IS NULL
WHERE
    aa.reviewed_at IS NULL
        AND aa.finish IS NOT NULL
        AND aa.deleted_at IS NULL
        AND aa.assess_id IN (SELECT
            a.assess_id
        FROM
            assess a
                JOIN
            assess_section ass ON a.assess_id = ass.assess_id
                JOIN
            assess_question aq ON ass.section_id = aq.section_id
                AND aq.deleted_at IS NULL
                JOIN
            assess_luQuestionType lqt ON aq.type_id = lqt.type_id
                AND lqt.automated_marking = TRUE
        GROUP BY assess_id)
ORDER BY aa.finish DESC;" | wc -l)
  if [ "$check" -ne 0 ]; then
    date
    echo "reviewing $check items"
    list=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "
SELECT
    aa.attempt_id
FROM
    sbc3d844bfe.assess_attempt aa
        JOIN
    sbc3d844bfe.assess a ON aa.assess_id = a.assess_id
        AND a.availability = 'assess'
        JOIN
    user u ON aa.user_id = u.id
        AND u.deleted_at IS NULL
        JOIN
    folder c ON a.category_id = c.id
        AND c.deleted_at IS NULL
WHERE
    aa.reviewed_at IS NULL
        AND aa.finish IS NOT NULL
        AND aa.deleted_at IS NULL
        AND aa.assess_id IN (SELECT
            a.assess_id
        FROM
            assess a
                JOIN
            assess_section ass ON a.assess_id = ass.assess_id
                JOIN
            assess_question aq ON ass.section_id = aq.section_id
                AND aq.deleted_at IS NULL
                JOIN
            assess_luQuestionType lqt ON aq.type_id = lqt.type_id
                AND lqt.automated_marking = TRUE
        GROUP BY assess_id)
ORDER BY aa.finish DESC;")
    for attempt in $list; do
      echo "-- reviewing attempt $attempt"
      review=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "UPDATE assess_attempt SET reviewed_at = finish WHERE attempt_id='$attempt';" 2>&1)
      if [ $? -ne 0 ]; then
        echo "Error: $review"
      fi
    done
  fi
}

main (){
  parse_args "$@"
  review
}

main "$@"
