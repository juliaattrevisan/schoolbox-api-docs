UPDATE config
SET field_id = (SELECT id FROM lu_config_field WHERE field = 'password')
WHERE name IN ('canvas_data2_secret', 'canvas_bearer_token');
