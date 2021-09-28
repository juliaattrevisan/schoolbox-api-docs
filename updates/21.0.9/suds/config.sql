#
# maximum time used to hold a lock before killing the process used for school data imports
#
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
    'schooldata_import_timeout',
    '10800',
    'Import Timeout',
    'Imports will automatically close after this many seconds have expired.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    null,
    'true',
    'false',
    'false',
    'schoolData',
    (SELECT MAX(sequence)+1 FROM config c WHERE module = 'schoolData')
);

