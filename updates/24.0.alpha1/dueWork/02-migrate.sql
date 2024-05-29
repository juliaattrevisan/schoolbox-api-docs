UPDATE submission_box
    SET enable_files = 1,
    enable_offline = 1,
    enable_text = 1,
    enable_policy = (select if(STRCMP(value, ''), 1, 0) from config where name = 'lms_student_policy')
WHERE enable_files = 0;
