-- Assessment Feedback

create or replace view v_assessment_feedback as
(select `sb`.`assessment_id` AS `assessment_id`,
        'submission_return'  AS `feedback_table`,
        `sr`.`id`            AS `feedback_id`,
        `sr`.`owner`         AS `student_id`,
        `sr`.`review_route`  AS `review_route`,
        `sr`.`published_at`  AS `published_at`
 from (`schoolbox`.`submission_return` `sr` join `schoolbox`.`submission_box` `sb` on (`sr`.`box_id` = `sb`.`id`))
 where `sr`.`deleted_at` is null)
union
(select `ass`.`assessment_id` AS `assessment_id`,
        'assess_attempt'      AS `feedback_table`,
        `aa`.`attempt_id`     AS `feedback_id`,
        `aa`.`user_id`        AS `student_id`,
        'instructor'          AS `review_route`,
        `aa`.`reviewed_at`    AS `published_at`
 from (`schoolbox`.`assess_attempt` `aa` join `schoolbox`.`assess` `ass` on (`aa`.`assess_id` = `ass`.`assess_id`))
 where `aa`.`deleted_at` is null
   and `aa`.`reviewed_at` is not null
   and `ass`.`assessment_id` is not null)
union
(select `t`.`assessment_id`    AS `assessment_id`,
        'task_completion'      AS `feedback_table`,
        `tc`.`id`              AS `feedback_id`,
        `tc`.`student_id`      AS `student_id`,
        'instructor'           AS `review_route`,
        `tc`.`completion_date` AS `published_at`
 from (`schoolbox`.`task_completion` `tc` join `schoolbox`.`task` `t` on (`tc`.`task_id` = `t`.`id`))
 where `tc`.`deleted_at` is null
   and `tc`.`completion_date` is not null
   and `tc`.`completion_status` = 3)
union
(select `la`.`assessment_id`   AS `assessment_id`,
        'lti_assessment_score' AS `feedback_table`,
        `sc`.`id`              AS `feedback_id`,
        `sc`.`student_id`      AS `student_id`,
        'instructor'           AS `review_route`,
        `sc`.`created_at`      AS `published_at`
 from (`schoolbox`.`lti_assessment_score` `sc` join `schoolbox`.`lti_assessment` `la`
       on (`sc`.`lti_assessment_id` = `la`.`id`))
 where `sc`.`deleted_at` is null
   and `sc`.`is_feedback` = 1)
union
(select `reporting`.`assessment_id` AS `assessment_id`,
        'reporting_feedback' AS `feedback_table`,
        `reporting_feedback`.`id` AS `feedback_id`,
        `reporting_feedback`.`learner_id` AS `student_id`,
        'instructor' AS `review_route`,
        `reporting_feedback`.`published_at` AS `published_at`
 from (`schoolbox`.`reporting_feedback` join `schoolbox`.`reporting`
       on (`reporting_feedback`.`reporting_id` = `reporting`.`id`))
 where `reporting_feedback`.`deleted_at` is null)
order by `assessment_id`, `student_id`;


-- Most Recent Feedback

create or replace view v_most_recent_feedback as
select `sb`.`assessment_id` AS `assessment_id`,
       'submission_return'  AS `feedback_table`,
       max(`sr1`.`id`)      AS `feedback_id`,
       `sr1`.`owner`        AS `student_id`,
       `sr1`.`review_route` AS `review_route`,
       `sr1`.`published_at` AS `published_at`
from ((`schoolbox`.`submission_return` `sr1` join (select `schoolbox`.`submission_return`.`box_id`            AS `box_id`,
                                                          `schoolbox`.`submission_return`.`owner`             AS `owner`,
                                                          `schoolbox`.`submission_return`.`review_route`      AS `review_route`,
                                                          max(`schoolbox`.`submission_return`.`published_at`) AS `max_published_at`
                                                   from `schoolbox`.`submission_return`
                                                   where `schoolbox`.`submission_return`.`deleted_at` is null
                                                     and `schoolbox`.`submission_return`.`published_at` is not null
                                                   group by `schoolbox`.`submission_return`.`box_id`,
                                                            `schoolbox`.`submission_return`.`owner`,
                                                            `schoolbox`.`submission_return`.`review_route`) `t1`
       on (`t1`.`box_id` = `sr1`.`box_id` and `t1`.`owner` = `sr1`.`owner` and
           `t1`.`review_route` = `sr1`.`review_route` and
           `sr1`.`published_at` = `t1`.`max_published_at`)) join `schoolbox`.`submission_box` `sb`
      on (`sr1`.`box_id` = `sb`.`id`))
group by `sb`.`assessment_id`, `sr1`.`owner`, `sr1`.`review_route`
union
select `a`.`assessment_id`     AS `assessment_id`,
       'assess_attempt'        AS `feedback_table`,
       max(`aa1`.`attempt_id`) AS `feedback_id`,
       `aa1`.`user_id`         AS `student_id`,
       'instructor'            AS `review_route`,
       `aa1`.`reviewed_at`     AS `published_at`
from ((`schoolbox`.`assess_attempt` `aa1` join (select `schoolbox`.`assess_attempt`.`assess_id`        AS `assess_id`,
                                                       `schoolbox`.`assess_attempt`.`user_id`          AS `user_id`,
                                                       max(`schoolbox`.`assess_attempt`.`reviewed_at`) AS `max_reviewed_at`
                                                from `schoolbox`.`assess_attempt`
                                                where `schoolbox`.`assess_attempt`.`reviewed_at` is not null
                                                  and `schoolbox`.`assess_attempt`.`deleted_at` is null
                                                group by `schoolbox`.`assess_attempt`.`assess_id`,
                                                         `schoolbox`.`assess_attempt`.`user_id`) `t1`
       on (`t1`.`assess_id` = `aa1`.`assess_id` and `t1`.`user_id` = `aa1`.`user_id` and
           `aa1`.`reviewed_at` = `t1`.`max_reviewed_at`)) join `schoolbox`.`assess` `a`
      on (`aa1`.`assess_id` = `a`.`assess_id`))
group by `a`.`assessment_id`, `aa1`.`user_id`
union
select `t`.`assessment_id`    AS `assessment_id`,
       'task_completion'      AS `feedback_table`,
       max(`tc`.`id`)         AS `feedback_id`,
       `tc`.`student_id`      AS `student_id`,
       'instructor'           AS `review_route`,
       `tc`.`completion_date` AS `published_at`
from ((`schoolbox`.`task_completion` `tc` join (select `schoolbox`.`task_completion`.`task_id`              AS `task_id`,
                                                       `schoolbox`.`task_completion`.`student_id`           AS `student_id`,
                                                       max(`schoolbox`.`task_completion`.`completion_date`) AS `max_completion_date`
                                                from `schoolbox`.`task_completion`
                                                where `schoolbox`.`task_completion`.`completion_status` = 3
                                                  and `schoolbox`.`task_completion`.`deleted_at` is null
                                                group by `schoolbox`.`task_completion`.`task_id`,
                                                         `schoolbox`.`task_completion`.`student_id`) `t1`
       on (`tc`.`task_id` = `t1`.`task_id` and `tc`.`student_id` = `t1`.`student_id` and
           `tc`.`completion_date` = `t1`.`max_completion_date`)) join `schoolbox`.`task` `t`
      on (`t`.`id` = `tc`.`task_id`))
group by `t`.`assessment_id`, `tc`.`student_id`
union
select `la`.`assessment_id`   AS `assessment_id`,
       'lti_assessment_score' AS `feedback_table`,
       max(`las`.`id`)        AS `feedback_id`,
       `las`.`student_id`     AS `student_id`,
       'instructor'           AS `review_route`,
       `las`.`created_at`     AS `published_at`
from ((`schoolbox`.`lti_assessment_score` `las` join (select `schoolbox`.`lti_assessment_score`.`lti_assessment_id` AS `lti_assessment_id`,
                                                             `schoolbox`.`lti_assessment_score`.`student_id`        AS `student_id`,
                                                             max(`schoolbox`.`lti_assessment_score`.`created_at`)   AS `max_created_at`
                                                      from `schoolbox`.`lti_assessment_score`
                                                      where `schoolbox`.`lti_assessment_score`.`deleted_at` is null
                                                        and `schoolbox`.`lti_assessment_score`.`is_feedback` = 1) `t1`
       on (`las`.`lti_assessment_id` = `t1`.`lti_assessment_id` and `las`.`student_id` = `t1`.`student_id` and
           `las`.`created_at` = `t1`.`max_created_at`)) join `schoolbox`.`lti_assessment` `la`
      on (`las`.`lti_assessment_id` = `la`.`id`))
group by `la`.`assessment_id`, `las`.`student_id`
union
SELECT
    `reporting`.`assessment_id` AS `assessment_id`,
    'reporting_feedback' AS `feedback_table`,
    max(`reporting_feedback`.`id`) AS `feedback_id`,
    `reporting_feedback`.`learner_id` AS `student_id`,
    'instructor' AS `review_route`,
    `reporting_feedback`.`published_at` AS `published_at`
FROM (
         (`schoolbox`.`reporting_feedback`
             INNER JOIN (
                 SELECT
                     `schoolbox`.`reporting_feedback`.`reporting_id` AS `reporting_id`,
                     `schoolbox`.`reporting_feedback`.`learner_id` AS `student_id`,
                     MAX(`schoolbox`.`reporting_feedback`.`published_at`) AS `max_published_at`
                 FROM `schoolbox`.`reporting_feedback`
                 WHERE `schoolbox`.`reporting_feedback`.`deleted_at` IS NULL
                 GROUP BY `schoolbox`.`reporting_feedback`.`reporting_id`, `schoolbox`.`reporting_feedback`.`learner_id`
             ) `t1`
          ON (
              `reporting_feedback`.`reporting_id` = `t1`.`reporting_id`
                  AND `reporting_feedback`.`learner_id` = `t1`.`student_id`
                  AND `reporting_feedback`.`created_at` = `t1`.`max_published_at`
              )
             )
             INNER JOIN `schoolbox`.`reporting`
         ON (`reporting_feedback`.`reporting_id` = `reporting`.`id`)
         )
GROUP BY `reporting`.`assessment_id`, `reporting_feedback`.`learner_id`;

