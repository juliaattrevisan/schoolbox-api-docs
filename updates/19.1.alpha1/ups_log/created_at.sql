alter table ups_log
    add created_at datetime null after users_skipped;

update ups_log set created_at = NOW();
