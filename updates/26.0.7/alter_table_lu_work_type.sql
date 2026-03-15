ALTER TABLE lu_work_type ADD COLUMN is_visible_to_parents tinyint(1) NOT NULL DEFAULT 0 AFTER staff_or_instructor_visible_only;
ALTER TABLE lu_work_type ADD COLUMN is_visible_to_students tinyint(1) NOT NULL DEFAULT 0 AFTER is_visible_to_parents;
ALTER TABLE lu_work_type CHANGE COLUMN staff_or_instructor_visible_only _staff_or_instructor_visible_only tinyint(1) NOT NULL;
