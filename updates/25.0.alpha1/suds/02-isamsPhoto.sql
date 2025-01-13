### add new column for isams photo use
alter table sis_user_profile
    add metadata varchar(255) collate utf8mb4_unicode_ci null after portrait_hash;