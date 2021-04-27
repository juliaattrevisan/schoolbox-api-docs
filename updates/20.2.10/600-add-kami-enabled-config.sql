INSERT INTO config(
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required
) VALUES (
    'kami_enabled',
    -- Enable if pro, elite, or unknown (err on side of caution, puppet plan manager will disable if required)
    (SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND COALESCE(value,'') IN ('','pro','elite')) t1),
    'Kami enabled',
    'Kami inline document editor',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Modules'),
    (IF((SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND COALESCE(value,'') IN ('','pro','elite')) t1), 'true', 'false')),
    'false',
    'true'
);
