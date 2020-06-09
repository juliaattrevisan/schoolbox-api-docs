UPDATE config SET sequence = 9 WHERE name = 'teams_enabled';
UPDATE config SET sequence = 30 WHERE name = 'calendar_api_caching';

UPDATE config SET sequence = sequence+3 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = '3rd Party Services') and sequence is not null;
UPDATE config SET sequence = 1 WHERE name = 'otfs_adapter';
UPDATE config SET sequence = 2 WHERE name = 'otfs_s3_presigned_urls_enabled';
UPDATE config SET sequence = 3 WHERE name = 'otfs_image_s3_presigned_urls_enabled';

UPDATE config SET sequence = sequence+2 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'External DB') and sequence >= 30;
UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'External DB') and sequence >= 39;

UPDATE config SET hidden = 'false', sequence = 30 WHERE name = 'external_tass_sso_server';
UPDATE config SET hidden = 'false', sequence = 31 WHERE name = 'external_tass_sso_token_key';
UPDATE config SET hidden = 'true' WHERE name = 'external_tass_app_code_parent_lounge';
UPDATE config SET hidden = 'true' WHERE name = 'external_tass_app_code_tassweb';
UPDATE config SET hidden = 'true' WHERE name = 'external_tass_app_code_teacherkiosk';
UPDATE config SET hidden = 'true' WHERE name = 'external_tass_app_code_webbook';
UPDATE config SET hidden = 'false', sequence = 32 WHERE name = 'external_tass_attendance_show_prod_menu';
UPDATE config SET hidden = 'false', sequence = 39 WHERE name = 'external_tass_parent_id_length';

UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'LMS Policy') and sequence >= 10;
UPDATE config SET sequence = 10 WHERE name = 'lms_resubmit_window';

UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Localisation') and sequence >= 1;
UPDATE config SET sequence = 1 WHERE name = 'spellcheck_language';

UPDATE config SET sequence = sequence+3 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Modules') and sequence >= 1;
UPDATE config SET sequence = 1 WHERE name = 'messaging_enabled';
UPDATE config SET sequence = 2 WHERE name = 'push_notifications_enabled';
UPDATE config SET sequence = 3 WHERE name = 'ups_groups_enabled';

UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Policy') and sequence >= 22;
UPDATE config SET sequence = 22 WHERE name = 'email_housemasters';

UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Policy') and sequence >= 27;
UPDATE config SET sequence = 27 WHERE name = 'show_boarder_block';

UPDATE config SET hidden='true' WHERE name = 'webdav_fallback_enabled';



UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Resources') and sequence >= 3;

UPDATE config SET sequence = 3 WHERE name = 'otfs_verify_previous_file';
UPDATE config SET sequence = 10 WHERE name = 'browser_cache_image_timeout';
UPDATE config SET sequence = 11 WHERE name = 'browser_cache_portrait_timeout';
UPDATE config SET sequence = 12 WHERE name = 'webdav_enable';
UPDATE config SET sequence = 13 WHERE name = 'webdav_auth_type';

UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Board Leave') and sequence >= 2;
UPDATE config SET sequence = 2 WHERE name = 'boarder_leave_parent_notification_enabled';

