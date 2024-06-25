# Bring the Kami worksheet columns into line with other column names.
alter table submission_box
    change kami_interactive enable_worksheet tinyint(1) default 0 not null,
    change kami_interactive_hash worksheet_template_hash varchar(100) charset ascii null;
