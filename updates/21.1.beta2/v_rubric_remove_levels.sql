DROP VIEW v_rubric;

create definer = schoolboxvagrant@localhost view v_rubric as
select `a`.`id`                                AS `assessment_id`,
       `a`.`title`                             AS `assessment_title`,
       `sb`.`mark_type`                        AS `assessment_mark_type`,
       `sb`.`max_mark`                         AS `assessment_max_mark`,
       `sb`.`submission_grade_group_id`        AS `assessment_grade_group_id`,
       `a`.`weight`                            AS `assessment_weight`,
       `rc`.`id`                               AS `capability_id`,
       `rc`.`name`                             AS `capability_name`,
       `rcu`.`max_value`                       AS `capability_max_value`,
       `rcu`.`sequence`                        AS `capability_sequence`,
       `sb`.`rubric_capability_mark_type_id`   AS `capability_mark_type_id`,
       `sb`.`rubric_capability_grade_group_id` AS `capability_grade_group_id`,
       `ri`.`id`                               AS `indicator_id`,
       `ri`.`name`                             AS `indicator_name`,
       `riu`.`sequence`                        AS `indicator_sequence`,
       `riu`.`max_value`                       AS `indicator_max_value`
from (((((`schoolbox`.`assessment` `a` join `schoolbox`.`submission_box` `sb` on (`a`.`id` = `sb`.`assessment_id`)) join `schoolbox`.`rubric_capability_usage` `rcu` on (`sb`.`id` = `rcu`.`submission_box_id`)) join `schoolbox`.`rubric_capability` `rc` on (`rcu`.`rubric_capability_id` = `rc`.`id`)) join `schoolbox`.`rubric_indicator_usage` `riu` on (`sb`.`id` = `riu`.`submission_box_id`))
         join `schoolbox`.`rubric_indicator` `ri`
              on (`riu`.`rubric_indicator_id` = `ri`.`id` and `ri`.`rubric_capability_id` = `rc`.`id`))
where `a`.`deleted_at` is null
  and `sb`.`use_rubric` is true
  and `rc`.`deleted_at` is null
  and `ri`.`deleted_at` is null
order by `a`.`id`, `rcu`.`sequence`, `rc`.`id`, `riu`.`sequence`, `ri`.`id`;
