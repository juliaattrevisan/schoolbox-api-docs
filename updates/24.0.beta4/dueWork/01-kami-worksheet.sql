alter table student_submission_file
    add is_worksheet TINYINT(1) default 0 null after editable_inline;

alter table submission_return_file
    add is_worksheet TINYINT(1) default 0 null after uuid;

alter table kami_document
    add job_reference VARCHAR(255) NULL after kami_document_id,
    add poll_count tinyint unsigned null after job_reference,
    modify status enum ('queued', 'uploading', 'failed', 'processing', 'done', 'error', 'pending') not null;
