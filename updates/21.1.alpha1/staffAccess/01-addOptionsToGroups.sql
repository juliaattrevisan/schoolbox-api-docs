ALTER TABLE groups
    ADD COLUMN view_student_profile TINYINT(1) DEFAULT 0 AFTER can_change_password,
    ADD COLUMN view_student_grades TINYINT(1) DEFAULT 0 AFTER view_student_profile,
    ADD COLUMN view_student_calendar TINYINT(1) DEFAULT 0 AFTER view_student_grades,
    ADD COLUMN view_parent_profile TINYINT(1) DEFAULT 0 AFTER view_student_calendar,
    ADD COLUMN access_eportfolio TINYINT(1) DEFAULT 0 AFTER view_parent_profile,
    ADD COLUMN access_pastoral TINYINT(1) DEFAULT 0 AFTER access_eportfolio,
    ADD COLUMN create_badges TINYINT(1) DEFAULT 0 AFTER access_pastoral,
    ADD COLUMN create_evidence TINYINT(1) DEFAULT 0 AFTER create_badges,
    ADD COLUMN view_parent_directory TINYINT(1) DEFAULT 0 AFTER create_evidence,
    ADD COLUMN view_staff_directory TINYINT(1) DEFAULT 0 AFTER view_parent_directory;
UPDATE TABLE groups
SET view_student_profile  = 1,
    view_student_grades   = 1,
    view_student_calendar = 1,
    view_parent_profile   = 1,
    access_eportfolio     = 1,
    access_pastoral       = 1,
    create_badges         = 1,
    create_evidence       = 1,
    view_parent_directory = 1,
    view_staff_directory  = 1
WHERE group_type = 'staff';
