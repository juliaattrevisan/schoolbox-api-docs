alter table submission_box
    add kami_interactive tinyint(1) default 0 null after multi_submission,
    add enable_offline tinyint(1) default 0 null after kami_interactive,
    add enable_text tinyint(1) default 0 null after enable_offline,
    add enable_files tinyint(1) default 0 null after enable_text,
    add enable_policy tinyint(1) default 0 null after enable_files;

