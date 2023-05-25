-- The `v_assessment_participation` view is the only one which uses
-- `v_assessment_relation`, thus it's the only one to update.
create or replace view v_assessment_participation as
select `a`.`id`                                          AS `assessment_id`,
       `a`.`deleted_at`                                  AS `assessment_deleted_at`,
       `p`.`user_id`                                     AS `student_id`,
       `var`.`folder_id`                                 AS `folder_id`,
       `pp`.`is_dynamic`                                 AS `is_dynamic`,
       coalesce(`pp`.`description`, `a`.`description`)   AS `description`,
       coalesce(`pp`.`publish_date`, `a`.`publish_date`) AS `publish_date`,
       coalesce(`pp`.`open_date`, `a`.`open_date`)       AS `open_date`,
       coalesce(`pp`.`due_date`, `a`.`due_date`)         AS `due_date`,
       coalesce(`pp`.`close_date`, `a`.`close_date`)     AS `close_date`,
       `p`.`excluded_at`                                 AS `excluded_at`
from (((`assessment` `a`
    join `assessment_relation` `var` on (
        `a`.`id` = `var`.`assessment_id`
        and `var`.`course_specification_id` is null
        and `var`.`course_unit_specification_id` is null
    ))
    join `participant_plan` `pp` on (`a`.`id` = `pp`.`assessment_id` and `pp`.`deleted_at` is null))
    join `participant` `p` on (`pp`.`id` = `p`.`participant_plan_id`));
