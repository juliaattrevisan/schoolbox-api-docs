ALTER TABLE `assess`
    ADD KEY `idx_assess_learnosity_job_status_deleted_created_id`
        (`learnosity_job_status`, `deleted_at`, `created_at`, `assess_id`);

ALTER TABLE `ib_registration_session_student`
    ADD KEY `idx_ib_rss_status_updated_id` (`status`, `updated_at`, `id`);
