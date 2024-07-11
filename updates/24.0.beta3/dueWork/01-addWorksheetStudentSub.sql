alter table student_submission_file
    add is_worksheet TINYINT(1) default 0 null after editable_inline;
