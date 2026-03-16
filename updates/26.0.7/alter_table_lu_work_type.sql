ALTER TABLE lu_work_type ADD COLUMN is_visible_to_parents tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER staff_or_instructor_visible_only;
ALTER TABLE lu_work_type ADD COLUMN is_visible_to_students tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER is_visible_to_parents;
ALTER TABLE lu_work_type CHANGE COLUMN staff_or_instructor_visible_only _staff_or_instructor_visible_only tinyint(1) NOT NULL;

UPDATE lu_work_type SET is_visible_to_parents = IF(_staff_or_instructor_visible_only > 0, 0, 1);
UPDATE lu_work_type SET is_visible_to_students = IF(_staff_or_instructor_visible_only > 0, 0, 1);

ALTER TABLE lu_work_type ADD COLUMN permitted_project tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER is_visible_to_students;
ALTER TABLE lu_work_type ADD COLUMN permitted_due_work tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_project;
ALTER TABLE lu_work_type ADD COLUMN permitted_task tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_due_work;
ALTER TABLE lu_work_type ADD COLUMN permitted_quiz tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_task;
ALTER TABLE lu_work_type ADD COLUMN permitted_lti tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_quiz;
ALTER TABLE lu_work_type ADD COLUMN permitted_lesson_plan tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_lti;
ALTER TABLE lu_work_type ADD COLUMN permitted_dialog_agent tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_lesson_plan;

-- All existing work types should have all activities permitted.
UPDATE lu_work_type SET
permitted_project      = 1,
permitted_due_work     = 1,
permitted_task         = 1,
permitted_quiz         = 1,
permitted_lti          = 1,
permitted_lesson_plan  = 1,
permitted_dialog_agent = 1;
