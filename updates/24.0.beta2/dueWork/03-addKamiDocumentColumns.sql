# Kami documents are not just student submissions anymore.
alter table kami_document
    modify student_submission_file_id int(11) unsigned null;

# They may now be worksheets
alter table kami_document
    add worksheet_template_submission_box_id int unsigned null after student_submission_file_id;
alter table kami_document
    add constraint kami_document_submission_box_id_fk
        foreign key (worksheet_template_submission_box_id) references submission_box (id);

# Also add a type
# Apply the default only long enough to set the value: all existing Kami docs
# are annotations.
alter table kami_document
    add type enum ('annotation', 'worksheet_template', 'worksheet') not null default 'annotation' after id;
alter table kami_document
    alter column type drop default;
