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

