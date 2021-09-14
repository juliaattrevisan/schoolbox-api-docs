#
# maximum time used to hold a lock before killing the process used for school data imports
#
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
    'schooldata_import_timeout',
    '',
    'Import Timeout',
    'School Data Import Timeout',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'Site'),
    'false',
    'true',
    'false'
);

