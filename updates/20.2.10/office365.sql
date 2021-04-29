UPDATE config
SET field_id = (SELECT id FROm lu_config_field WHERE field = 'Password')
WHERE `name` = 'office365_secret';