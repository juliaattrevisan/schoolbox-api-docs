-- Copy the view's statement directly into a REPLACE statement, thus maintaining
-- unique assessment IDs as much as I can.

-- First set max statement time here: this could take a while
SET MAX_STATEMENT_TIME=0;

-- Assessments directly in a folder
REPLACE INTO mv_assessment_relation
select `a`.`id` AS `assessment_id`,
       `f`.`id` AS `folder_id`,
       NULL     AS `project_assessment_id`,
       NULL     AS `course_implementation_id`,
       NULL     AS `course_unit_implementation_id`,
       NULL     AS `course_specification_id`,
       NULL     AS `course_unit_specification_id`
from ((`assessment` `a`
    join `assessment_folder` `af` on (`a`.`id` = `af`.`assessment_id`))
    join `folder` `f` on (`af`.`folder_id` = `f`.`id`));

-- Assessments in a course implementation
REPLACE INTO mv_assessment_relation
select `a`.`id`   AS `assessment_id`,
       `f`.`id`   AS `folder_id`,
       NULL       AS `project_assessment_id`,
       `ci`.`id`  AS `course_implementation_id`,
       `cui`.`id` AS `course_unit_implementation_id`,
       NULL       AS `course_specification_id`,
       NULL       AS `course_unit_specification_id`
from ((((`assessment` `a`
    join `assessment_course_unit_implementation` `acui` on (`a`.`id` = `acui`.`assessment_id`))
    join `course_unit_implementation` `cui` on (`acui`.`course_unit_implementation_id` = `cui`.`id`))
    join `course_implementation` `ci` on (`cui`.`course_implementation_id` = `ci`.`id`))
    join `folder` `f` on (`ci`.`folder_id` = `f`.`id`));

-- Assessments in a course spec
REPLACE INTO mv_assessment_relation
select `a`.`id`   AS `assessment_id`,
       `f`.`id`   AS `folder_id`,
       NULL       AS `project_assessment_id`,
       NULL       AS `course_implementation_id`,
       NULL       AS `course_unit_implementation_id`,
       `cs`.`id`  AS `course_specification_id`,
       `cus`.`id` AS `course_unit_specification_id`
from ((((`assessment` `a`
    join `assessment_course_unit_specification` `acus` on (`a`.`id` = `acus`.`assessment_id`))
    join `course_unit_specification` `cus` on (`acus`.`course_unit_specification_id` = `cus`.`id`))
    join `course_specification` `cs` on (`cus`.`course_specification_id` = `cs`.`id`))
    join `folder` `f` on (`cus`.`folder_id` = `f`.`id`));

-- Assessments in a project in a folder
REPLACE INTO mv_assessment_relation
select `a`.`id` AS `assessment_id`,
       `f`.`id` AS `folder_id`,
       `p`.`id` AS `project_assessment_id`,
       NULL     AS `course_implementation_id`,
       NULL     AS `course_unit_implementation_id`,
       NULL     AS `course_specification_id`,
       NULL     AS `course_unit_specification_id`
from ((((`assessment` `a`
    join `project_assessment` `pa` on (`a`.`id` = `pa`.`assessment_id`))
    join `project` `p` on (`pa`.`project_id` = `p`.`id`))
    join `assessment_folder` `af` on (`p`.`assessment_id` = `af`.`assessment_id`))
    join `folder` `f` on (`af`.`folder_id` = `f`.`id`));

-- Assessments in a project in a course implementation
REPLACE INTO mv_assessment_relation
select `a`.`id`   AS `assessment_id`,
       `f`.`id`   AS `folder_id`,
       `p`.`id`   AS `project_assessment_id`,
       `ci`.`id`  AS `course_implementation_id`,
       `cui`.`id` AS `course_unit_implementation_id`,
       NULL       AS `course_specification_id`,
       NULL       AS `course_unit_specification_id`
from ((((((`assessment` `a`
    join `project_assessment` `pa` on (`a`.`id` = `pa`.`assessment_id`))
    join `project` `p` on (`pa`.`project_id` = `p`.`id`))
    join `assessment_course_unit_implementation` `acui` on (`p`.`assessment_id` = `acui`.`assessment_id`))
    join `course_unit_implementation` `cui` on (`acui`.`course_unit_implementation_id` = `cui`.`id`))
    join `course_implementation` `ci` on (`cui`.`course_implementation_id` = `ci`.`id`))
    join `folder` `f` on (`ci`.`folder_id` = `f`.`id`));

-- Assessments in a project in a course spec
REPLACE INTO mv_assessment_relation
select `a`.`id`   AS `assessment_id`,
       `f`.`id`   AS `folder_id`,
       `p`.`id`   AS `project_assessment_id`,
       NULL       AS `course_implementation_id`,
       NULL       AS `course_unit_implementation_id`,
       `cs`.`id`  AS `course_specification_id`,
       `cus`.`id` AS `course_unit_specification_id`
from ((((((`assessment` `a`
    join `project_assessment` `pa` on (`a`.`id` = `pa`.`assessment_id`))
    join `project` `p` on (`pa`.`project_id` = `p`.`id`))
    join `assessment_course_unit_specification` `acus` on (`p`.`assessment_id` = `acus`.`assessment_id`))
    join `course_unit_specification` `cus` on (`acus`.`course_unit_specification_id` = `cus`.`id`))
    join `course_specification` `cs` on (`cus`.`course_specification_id` = `cs`.`id`))
    join `folder` `f` on (`cus`.`folder_id` = `f`.`id`))
order by `assessment_id`;
