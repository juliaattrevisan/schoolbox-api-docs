UPDATE config SET hidden = 'false', sequence = (
    SELECT MAX(sequence) + 1
    FROM config c
    WHERE type_id = (
        SELECT id
        FROM lu_config_type
        WHERE type = '3rd Party Services'
    )),
    field_id = (SELECT id FROM lu_config_field WHERE field = 'textarea')
WHERE name like 'intellischool_jwt_private_key';
