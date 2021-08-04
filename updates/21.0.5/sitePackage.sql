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
    'site_package',
    '',
    'Site Package',
    'This is the package version of Schoolbox currently installed.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Site'),
    'false',
    'true',
    'false'
);
