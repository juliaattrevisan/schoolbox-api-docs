create view v_most_recent_rubric_mark as
select `sb`.`assessment_id`                                  AS `assessment_id`,
       `vr`.`capability_mark_type_id`                        AS `capability_mark_type_id`,
       `vr`.`capability_grade_group_id`                      AS `capability_grade_group_id`,
       `sr`.`owner`                                          AS `student_id`,
       `sr`.`marker`                                         AS `marker_id`,
       `sr`.`id`                                             AS `feedback_id`,
       `sr`.`published_at`                                   AS `feedback_date`,
       `sr`.`updated_at`                                     AS `updated_date`,
       `sr`.`review_route`                                   AS `feedback_type`,
       `vr`.`capability_id`                                  AS `capability_id`,
       `vr`.`capability_name`                                AS `capability_name`,
       `vr`.`capability_max_value`                           AS `capability_max_value`,
       if(`rcm`.`valid`, `rcm`.`value`, NULL)                AS `capability_mark`,
       if(`rcm`.`valid`, `rcm`.`human_readable_value`, NULL) AS `capability_human_readable_mark`,
       `vr`.`indicator_id`                                   AS `indicator_id`,
       `vr`.`indicator_name`                                 AS `indicator_name`,
       `vr`.`indicator_max_value`                            AS `indicator_max_value`,
       if(`rim`.`valid`, `rim`.`value`, NULL)                AS `indicator_mark`,
       `rim`.`descriptor_id`                                 AS `descriptor_id`,
       `rim`.`descriptor_name`                               AS `descriptor_name`
from ((((`v_rubric` `vr`
    join `submission_box` `sb` on (`vr`.`assessment_id` = `sb`.`assessment_id`))
    join `submission_return` `sr` on (`sb`.`id` = `sr`.`box_id`))
    join `rubric_capability_mark` `rcm` on (`rcm`.`return_id` = `sr`.`id` and `rcm`.`rubric_capability_id` = `vr`.`capability_id`))
    left join `v_rubric_indicator_mark_extend` `rim` on (`rim`.`rubric_indicator_id` = `vr`.`indicator_id` and `rcm`.`id` = `rim`.`rubric_capability_mark_id`))

where `sr`.`deleted_at` is null
  and (`rcm`.`valid` is true or `rim`.`valid` is true)
order by `sb`.`assessment_id`, `sr`.`owner`, `sr`.`updated_at`, `vr`.`capability_sequence`, `vr`.`capability_id`, `vr`.`indicator_sequence`, `vr`.`indicator_id`;

