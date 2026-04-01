ALTER TABLE lu_work_type
ADD COLUMN is_visible_to_parents tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER staff_or_instructor_visible_only,
ADD COLUMN is_visible_to_learners tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER is_visible_to_parents,
ADD COLUMN permitted_project tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER is_visible_to_learners,
ADD COLUMN permitted_due_work tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_project,
ADD COLUMN permitted_task tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_due_work,
ADD COLUMN permitted_quiz tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_task,
ADD COLUMN permitted_lti tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_quiz,
ADD COLUMN permitted_lesson_plan tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_lti,
ADD COLUMN permitted_dialog_agent tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_lesson_plan;
