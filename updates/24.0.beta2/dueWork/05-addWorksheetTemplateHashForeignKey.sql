# Worksheet template should have a FK back to storage
alter table submission_box
    add constraint submission_box_storage_hash_fk
        foreign key (worksheet_template_hash) references storage (hash);
