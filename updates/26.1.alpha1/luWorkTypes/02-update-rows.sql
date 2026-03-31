UPDATE lu_work_type SET
is_visible_to_parents = IF(staff_or_instructor_visible_only > 0, 0, 1), -- to preserve existing behaviour
is_visible_to_students = IF(staff_or_instructor_visible_only > 0, 0, 1), -- to preserve existing behaviour
permitted_project      = 1, -- -+ All existing work types should have all activities permitted.
permitted_due_work     = 1, --  |
permitted_task         = 1, --  |
permitted_quiz         = 1, --  |
permitted_lti          = 1, --  |
permitted_lesson_plan  = 1, --  |
permitted_dialog_agent = 1; --  /
