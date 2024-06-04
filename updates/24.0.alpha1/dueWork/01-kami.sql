alter table submission_box
    add kami_interactive tinyint(1) default 0 not null after multi_submission,
    add kami_interactive_hash varchar(100) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' default null after kami_interactive,
    add enable_offline tinyint(1) default 0 not null after kami_interactive_hash,
    add enable_text tinyint(1) default 0 not null after enable_offline,
    add enable_files tinyint(1) default 0 not null after enable_text,
    add enable_policy tinyint(1) default 0 not null after enable_files;

