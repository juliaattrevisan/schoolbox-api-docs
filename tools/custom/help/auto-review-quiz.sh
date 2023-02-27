#!/bin/bash

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

review() {
  sql_host=$(schoolbox-read-instances --option SQL_host)
  sql_user=$(schoolbox-read-instances --option SQL_user)
  sql_pass=$(schoolbox-read-instances --option SQL_passwd)
  sql_db=$(schoolbox-read-instances --option SQL_db)

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
      review=$(mysql -h "$sql_host" -u "$sql_user" -p"$sql_pass" "$sql_db" -BNe "
      UPDATE assess_attempt,
          (SELECT
               if((SUM(ar.mark) >= 9999999999999.99), if((SUM(aq.marks) >= 9999999999999.99), 9999999999999.99, SUM(aq.marks)), SUM(ar.mark))  AS mark,
               if((SUM(aq.marks) >= 9999999999999.99), 9999999999999.99, SUM(aq.marks))  AS max_mark,
               aa.attempt_id
           FROM assess_attempt aa
                    JOIN assess q ON q.assess_id = aa.assess_id
                    LEFT JOIN assess_response ar ON aa.attempt_id  = ar.attempt_id
                    LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
           WHERE aa.attempt_id = '$attempt'
             AND ar.deleted_at IS NULL
             AND aq.deleted_at IS NULL
           GROUP BY aa.assess_id, aa.attempt_id) as newVal
      SET
          assess_attempt.max_mark = newVal.max_mark,
          assess_attempt.mark  = newVal.mark,
          assess_attempt.reviewed_at = assess_attempt.finish
      WHERE assess_attempt.attempt_id = newVal.attempt_id;
      " 2>&1)
      if [ $? -ne 0 ]; then
        echo "Error: $review"
      fi
    done
  fi
}

main (){
  export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games;
  parse_args "$@"
  review
}

main "$@"
