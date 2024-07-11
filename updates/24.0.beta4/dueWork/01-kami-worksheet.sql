alter table student_submission_file
    add is_worksheet TINYINT(1) default 0 null after editable_inline;

alter table kami_document
    add job_reference VARCHAR(255) NULL after kami_document_id,
    add poll_count tinyint unsigned null after job_reference;
