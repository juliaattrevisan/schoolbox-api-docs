INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
) VALUES (
    'lms_enabled',
    -- Enable if basic,pro,elite
    (SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value IN ('basic','elite','pro')) t1),
    'Enable LMS',
    'If ON, the LMS features will be configurable',
    (SELECT id FROM `lu_config_field` WHERE `field` = 'boolean'),
    null,
    (SELECT id FROM `lu_config_type` WHERE `type` = 'Modules'),
    'false',
    'true',
    'false',
    'settings',
    null
);

