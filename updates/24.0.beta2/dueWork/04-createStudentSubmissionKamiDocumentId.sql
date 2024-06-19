alter table student_submission_file
    add kami_document_id int null after hash;

alter table student_submission_file
    add constraint student_submission_file_kami_document_id_fk
        foreign key (kami_document_id) references kami_document (id);
