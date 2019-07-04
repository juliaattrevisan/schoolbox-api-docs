UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='external_cache_timeout';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='login_grace';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='login_allowed';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='session_length';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='submission_window';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='thumbnail_size';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='video_processing_timeout';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='image_slideshow_max';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='calendar_export_start';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='calendar_export_end';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='timetable_export_end';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='xml_cache_timeout';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='twitter_api_caching';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='calendar_api_caching';


UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='mail_smtp_port';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='rubric_max_levels';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='boarder_leave_creation_threshold';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='pastoral_upload_timeout';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='pastoral_page_size';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='search_all_limit';
UPDATE `config` SET field_id = (SELECT id FROM `lu_config_field` WHERE field = 'number') WHERE name='search_type_limit';


DELETE FROM config WHERE name = 'file_upload_count';



