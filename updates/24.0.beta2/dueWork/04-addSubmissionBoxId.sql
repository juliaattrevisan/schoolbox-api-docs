alter table kami_document
    modify student_submission_file_id int(11) unsigned null;

alter table kami_document
    add worksheet_template_submission_box_id int unsigned null after student_submission_file_id;

alter table kami_document
    add constraint kami_document_submission_box_id_fk
        foreign key (worksheet_template_submission_box_id) references submission_box (id);

