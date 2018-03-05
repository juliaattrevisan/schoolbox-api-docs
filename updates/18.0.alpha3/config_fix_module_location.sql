UPDATE config SET type_id=(SELECT id FROM lu_config_type WHERE type LIKE 'modules' LIMIT 0,1) WHERE name='pastoral_enabled';
