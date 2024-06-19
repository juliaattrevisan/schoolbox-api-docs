alter table student_submission_file_kami
    change kami_document_id _kami_document_id char(32) charset ascii null;

alter table student_submission_file_kami
    change status _status enum ('queued', 'uploading', 'failed', 'processing', 'done', 'error') not null;

alter table student_submission_file_kami
    change error_message _error_message varchar(2048) null;

alter table student_submission_file_kami
    change kami_created_at _kami_created_at datetime null;

alter table student_submission_file_kami
    change created_at _created_at datetime not null;

alter table student_submission_file_kami
    change updated_at _updated_at datetime not null;

alter table student_submission_file_kami
    change deleted_at _deleted_at datetime null;

alter table student_submission_file_kami
    drop key idx_student_submission_file_kami_kami_document_id;
