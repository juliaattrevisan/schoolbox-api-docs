### add new column for isams photo use
alter table sis_user_profile
    add metadata json collate utf8mb4_unicode_ci NULL DEFAULT NULL after portrait_hash;