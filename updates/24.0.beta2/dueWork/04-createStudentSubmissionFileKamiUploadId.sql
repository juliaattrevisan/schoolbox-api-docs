alter table student_submission_file_kami
    add kami_upload_id int null after student_submission_file_id;

alter table student_submission_file_kami
    add constraint student_submission_file_kami_kami_upload_id_fk
        foreign key (kami_upload_id) references kami_upload (id);
