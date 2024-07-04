alter table student_submission_file
    add isWorksheet TINYINT(1) default 0 null after editable_inline;
