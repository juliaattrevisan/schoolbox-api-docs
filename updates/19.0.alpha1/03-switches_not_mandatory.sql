UPDATE config c
INNER JOIN lu_config_field l
ON c.field_id = l.id
SET c.required = 'false'
WHERE l.field = 'boolean';
