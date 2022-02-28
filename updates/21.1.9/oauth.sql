INSERT INTO lu_config_field (id, field)
VALUES (19, 'uri');


UPDATE config SET field_id = (SELECT id FROM lu_config_field WHERE field = 'uri')
WHERE name = 'cloudwork_oauth_identity_provider';
