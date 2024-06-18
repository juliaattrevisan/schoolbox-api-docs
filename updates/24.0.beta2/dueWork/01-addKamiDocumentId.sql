alter table submission_box
    add kami_interactive_document_id char(32) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' default null after kami_interactive_hash;
